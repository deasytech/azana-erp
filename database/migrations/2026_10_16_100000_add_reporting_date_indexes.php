<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboards, reports, the breeding calendar and the period filters on lists all select by a date range on these columns.
 * Without an index each of those reads scans the whole table, which is fine on day one and slow after a year of records.
 */
return new class extends Migration
{
    /** @var array<string, string> table => date column */
    private const INDEXES = [
        'feed_consumption_records' => 'consumed_on',
        'weight_records' => 'weighed_at',
        'treatments' => 'administered_on',
        'vaccinations' => 'administered_on',
        'breeding_services' => 'serviced_on',
        'pregnancy_checks' => 'checked_on',
        'farrowings' => 'farrowed_on',
        'weaning_records' => 'weaned_on',
        'heat_events' => 'detected_on',
        'health_events' => 'observed_on',
        'litter_losses' => 'occurred_on',
        'animal_movements' => 'moved_at',
        'production_batch_events' => 'occurred_on',
        'production_costs' => 'incurred_on',
        'sales_orders' => 'dispatched_on',
        'supplier_invoices' => 'invoice_date',
        'carcasses' => 'slaughtered_at',
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column) && ! $this->indexed($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $t->index($column, "{$table}_{$column}_index"));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $column) {
            if (Schema::hasIndex($table, "{$table}_{$column}_index")) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex("{$table}_{$column}_index"));
            }
        }
    }

    private function indexed(string $table, string $column): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $index) => ($index['columns'][0] ?? null) === $column);
    }
};
