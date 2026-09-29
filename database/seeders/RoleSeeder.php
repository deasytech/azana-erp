<?php

namespace Database\Seeders;

use App\Enums\Module;
use App\Enums\PermissionAction as A;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the default roles. Role names/permissions remain editable in the
 * admin UI; this only seeds missing roles and never overwrites edits to existing ones.
 */
class RoleSeeder extends Seeder
{
    /** @var array<string, array{2fa: bool, desc: string}> */
    public const ROLES = [
        Role::OWNER => ['2fa' => true, 'desc' => 'Full oversight of the business.'],
        'General Manager' => ['2fa' => true, 'desc' => 'Runs day-to-day operations across modules.'],
        'Farm Manager' => ['2fa' => false, 'desc' => 'Manages farm operations.'],
        'Breeding Manager' => ['2fa' => false, 'desc' => 'Breeding, farrowing and litters.'],
        'Veterinarian' => ['2fa' => false, 'desc' => 'Animal health and biosecurity.'],
        'Semen Laboratory Manager' => ['2fa' => true, 'desc' => 'Semen production and QC release.'],
        'Feed Mill Manager' => ['2fa' => false, 'desc' => 'Feed formulation and production.'],
        'Store Officer' => ['2fa' => false, 'desc' => 'Inventory and stores.'],
        'Sales Officer' => ['2fa' => false, 'desc' => 'Customers and sales.'],
        'Slaughter Manager' => ['2fa' => false, 'desc' => 'Slaughter and meat processing.'],
        'Accountant' => ['2fa' => true, 'desc' => 'Finance and costing.'],
        'Farm Worker' => ['2fa' => false, 'desc' => 'Quick-entry of daily farm activity.'],
    ];

    /**
     * Default grants per role, as "module" => actions. A module listed as '*' grants all its actions.
     * New roles receive all of their defaults. Existing roles only receive defaults for permissions
     * that were created by this run (a new module), so admin edits to older grants are never reverted.
     * Owner/Director needs no entry: it passes every permission check.
     *
     * @var array<string, array<string, list<A>>>
     */
    private const GRANTS = [
        'General Manager' => [
            'users' => [A::View], 'audit-logs' => [A::View], 'login-activity' => [A::View],
            'farm-structure' => [A::View, A::Create, A::Edit, A::Export, A::Print],
            'master-data' => [A::View, A::Create, A::Edit, A::Export],
            'settings' => [A::View, A::Edit],
            'price-lists' => [A::View, A::Create, A::Edit, A::Approve, A::Export],
        ],
        'Farm Manager' => [
            'farm-structure' => [A::View, A::Create, A::Edit, A::Export, A::Print],
            'master-data' => [A::View], 'settings' => [A::View],
        ],
        'Breeding Manager' => ['farm-structure' => [A::View], 'master-data' => [A::View], 'settings' => [A::View]],
        'Veterinarian' => ['farm-structure' => [A::View], 'master-data' => [A::View]],
        'Semen Laboratory Manager' => ['farm-structure' => [A::View], 'master-data' => [A::View]],
        'Feed Mill Manager' => ['farm-structure' => [A::View], 'master-data' => [A::View]],
        'Store Officer' => ['farm-structure' => [A::View], 'master-data' => [A::View]],
        'Sales Officer' => ['farm-structure' => [A::View], 'master-data' => [A::View], 'price-lists' => [A::View]],
        'Slaughter Manager' => ['farm-structure' => [A::View], 'master-data' => [A::View]],
        'Accountant' => [
            'farm-structure' => [A::View], 'master-data' => [A::View],
            'price-lists' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
        ],
        'Farm Worker' => ['farm-structure' => [A::View], 'master-data' => [A::View]],
    ];

    public function run(): void
    {
        $created = (new PermissionSeeder)->createPermissions();

        foreach (self::ROLES as $name => $meta) {
            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['requires_two_factor' => $meta['2fa'], 'description' => $meta['desc']],
            );

            $defaults = $this->defaultsFor($name);

            $role->givePermissionTo($role->wasRecentlyCreated ? $defaults : array_values(array_intersect($defaults, $created)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return list<string> */
    private function defaultsFor(string $role): array
    {
        if ($role === Role::OWNER) {
            return $this->all();
        }

        return collect(self::GRANTS[$role] ?? [])
            ->flatMap(fn (array $actions, string $module) => array_map(fn (A $a) => "{$module}.{$a->value}", $actions))
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function all(): array
    {
        return collect(Module::cases())
            ->flatMap(fn (Module $m) => array_map(fn (A $a) => $m->permission($a), $m->actions()))
            ->all();
    }
}
