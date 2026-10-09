<?php

namespace App\Domain\System\Actions;

use App\Domain\Backup\Actions\CreateBackup;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\DataMode;
use App\Models\User;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Empties the system of practice (demo) records so real data can start from a clean slate, keeping everything that is
 * configuration: users, roles and permissions, the farm and its settings, production units, lookup values, units of measure,
 * breeds, feed types, the chart of accounts and cost centres. The standard stock items, stores and meat products are put back.
 *
 * Only allowed while the system is in demo mode (a farm setting the demo seeder sets and this action clears), so it cannot
 * wipe real records by accident. Every table is named in KEEP or WIPE; a test fails if a new table is in neither.
 */
class ResetToLiveData
{
    /** Configuration and infrastructure tables that survive the reset. */
    public const KEEP = [
        'accounts', 'backup_runs', 'breeds', 'cache', 'cache_locks', 'cost_centres', 'failed_jobs', 'farm_settings', 'farms', 'feed_types',
        'job_batches', 'jobs', 'login_activities', 'lookup_values', 'migrations', 'model_has_permissions', 'model_has_roles', 'password_reset_tokens',
        'permissions', 'personal_access_tokens', 'production_units', 'role_has_permissions', 'roles', 'sessions', 'units_of_measure', 'users',
    ];

    /** Records of work and the master data made for the practice run. */
    public const WIPE = [
        'animal_identifiers', 'animal_movements', 'animal_parentage', 'animal_photos', 'animal_status_history', 'animals', 'audit_logs', 'batch_weigh_ins',
        'biosecurity_check_items', 'biosecurity_checklist_items', 'biosecurity_checks', 'biosecurity_visits', 'breeding_services', 'budget_lines', 'budgets',
        'buildings', 'carcass_adjustments', 'carcasses', 'cash_transactions', 'culling_records', 'customers', 'data_import_rows', 'data_imports', 'diseases',
        'expense_records', 'farrowings', 'feed_consumption_records', 'feed_formula_items', 'feed_formulas', 'feed_production_batches', 'feed_production_order_lines',
        'feed_production_orders', 'genetic_lines', 'goods_receipt_lines', 'goods_receipts', 'health_events', 'heat_events', 'inventory_batches', 'inventory_items',
        'inventory_layers', 'inventory_locations', 'inventory_transactions', 'invoice_lines', 'invoices', 'journal_entries', 'journal_lines', 'kpi_targets',
        'laboratory_results', 'litter_losses', 'litters', 'locations', 'meat_production_batches', 'meat_production_lines', 'meat_products', 'medicine_batches',
        'medicines', 'mortality_records', 'notifications', 'number_sequences', 'payment_allocations', 'payments', 'pens', 'piglets', 'pregnancy_checks',
        'price_list_items', 'price_lists', 'production_batch_animals', 'production_batch_events', 'production_batches', 'production_costs', 'purchase_order_lines',
        'purchase_orders', 'purchase_request_lines', 'purchase_requests', 'quarantine_records', 'rooms', 'sales_order_lines', 'sales_orders', 'semen_batches',
        'semen_boars', 'semen_collections', 'semen_qc_records', 'slaughter_batches', 'slaughter_records', 'stock_adjustments', 'stock_count_lines', 'stock_counts',
        'stock_reservations', 'supplier_invoices', 'supplier_payments', 'suppliers', 'sync_mutations', 'task_assignments', 'task_evidence', 'tasks', 'treatments',
        'vaccination_schedules', 'vaccinations', 'veterinary_visits', 'weaning_records', 'website_enquiries', 'website_listings', 'weight_records', 'withdrawal_periods',
    ];

    /** Email domain of the accounts the demo set-up creates. */
    public const DEMO_EMAIL_SUFFIX = '@azana.test';

    public function __construct(private readonly ResolveSettings $settings, private readonly CreateBackup $backup, private readonly RecordAudit $audit) {}

    public function mode(): DataMode
    {
        return DataMode::tryFrom((string) $this->settings->get('system.data_mode')) ?? DataMode::Live;
    }

    public function allowed(): bool
    {
        return $this->mode() === DataMode::Demo;
    }

    /** @return array<string, int> rows that would be removed, by table (empty tables left out) */
    public function preview(): array
    {
        return collect(self::WIPE)->filter(fn ($t) => Schema::hasTable($t))
            ->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->filter()->all();
    }

    public function demoAccounts(?User $except = null): int
    {
        return User::where('email', 'like', '%'.self::DEMO_EMAIL_SUFFIX)->when($except, fn ($q) => $q->whereKeyNot($except->getKey()))->count();
    }

    /**
     * @return array{tables: int, rows: int, users: int, backup: ?string}
     */
    public function __invoke(User $by, bool $backupFirst = true, bool $removeDemoAccounts = false): array
    {
        $this->allowed() || throw new DomainException('This system is in live mode, so the practice data reset is switched off. It only works while the Data mode setting is "demo".', 'reset_not_allowed');

        $backup = $backupFirst ? $this->backup($by) : null;
        $rows = array_sum($this->preview());

        // Done first: it can fail on a foreign key, and that must happen before anything is deleted.
        $users = $removeDemoAccounts ? $this->removeDemoAccounts($by) : 0;

        $this->deleteFiles();

        $tables = collect(self::WIPE)->filter(fn ($t) => Schema::hasTable($t));

        Schema::disableForeignKeyConstraints();
        // SQLite ignores the switch above inside a transaction (as in the tests); this defers the checks to the end instead.
        DB::connection()->getDriverName() === 'sqlite' && DB::statement('PRAGMA defer_foreign_keys = ON');

        try {
            $tables->each(fn ($t) => DB::table($t)->truncate());
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // The standard stores, stock items for semen and meat, and meat products come back; nothing else is invented.
        (new MasterDataSeeder)->run();
        (new FinanceSeeder)->run();

        $this->settings->set('system.data_mode', DataMode::Live->value);
        $this->settings->flush();

        Auth::setUser($by);
        ($this->audit)('system.data_reset', null, null, ['tables' => $tables->count(), 'rows' => $rows, 'demo_accounts_removed' => $users, 'backup' => $backup], 'Practice data cleared for go-live');

        return ['tables' => $tables->count(), 'rows' => $rows, 'users' => $users, 'backup' => $backup];
    }

    private function backup(User $by): string
    {
        try {
            $run = ($this->backup)($by);
        } catch (\Throwable $e) {
            throw new DomainException('The backup before the reset failed, so nothing was removed: '.$e->getMessage(), 'reset_backup_failed');
        }

        return (string) $run->file;
    }

    /** Uploaded photos and import files belong to records that are about to disappear. */
    private function deleteFiles(): void
    {
        foreach ([['animal_photos', 'path', 'disk'], ['task_evidence', 'path', null], ['data_imports', 'path', null]] as [$table, $column, $diskColumn]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->select(array_filter([$column, $diskColumn]))->orderBy($column)->each(function ($row) use ($table, $column, $diskColumn) {
                $disk = $diskColumn ? $row->{$diskColumn} : ($table === 'task_evidence' ? 'public' : 'local');

                try {
                    Storage::disk($disk)->delete($row->{$column});
                } catch (\Throwable) {
                    // A file that is already gone or on an unreachable disk must not stop the reset.
                }
            });
        }
    }

    private function removeDemoAccounts(User $keep): int
    {
        $ids = User::where('email', 'like', '%'.self::DEMO_EMAIL_SUFFIX)->whereKeyNot($keep->getKey())->pluck('id');

        DB::table('backup_runs')->whereIn('created_by', $ids)->update(['created_by' => null]);
        DB::table('login_activities')->whereIn('user_id', $ids)->delete();
        DB::table('personal_access_tokens')->where('tokenable_type', (new User)->getMorphClass())->whereIn('tokenable_id', $ids)->delete();
        DB::table('model_has_roles')->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $ids)->delete();
        DB::table('model_has_permissions')->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $ids)->delete();
        DB::table('users')->whereIn('id', $ids)->delete();

        return $ids->count();
    }
}
