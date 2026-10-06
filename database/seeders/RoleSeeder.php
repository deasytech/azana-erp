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
        'Nutritionist' => ['2fa' => false, 'desc' => 'Designs and maintains feed formulas.'],
        'Store Officer' => ['2fa' => false, 'desc' => 'Inventory and stores.'],
        'Sales Officer' => ['2fa' => false, 'desc' => 'Customers and sales.'],
        'Slaughter Manager' => ['2fa' => false, 'desc' => 'Slaughter and meat processing.'],
        'Accountant' => ['2fa' => true, 'desc' => 'Finance and costing.'],
        'Farm Worker' => ['2fa' => false, 'desc' => 'Quick-entry of daily farm activity.'],
    ];

    private const FARM = 'farm-structure';

    private const MASTER = 'master-data';

    private const PRICES = 'price-lists';

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
            self::FARM => [A::View, A::Create, A::Edit, A::Export, A::Print],
            self::MASTER => [A::View, A::Create, A::Edit, A::Export],
            'settings' => [A::View, A::Edit],
            self::PRICES => [A::View, A::Create, A::Edit, A::Approve, A::Export],
            'animals' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
            'breeding' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
            'health' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
            'biosecurity' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
            'production' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
            'inventory' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
            'procurement' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
            'feed-mill' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
            'semen' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
        ],
        'Farm Manager' => [
            self::FARM => [A::View, A::Create, A::Edit, A::Export, A::Print],
            self::MASTER => [A::View], 'settings' => [A::View],
            'animals' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
            'breeding' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
            'health' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
            'biosecurity' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
            'production' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
            'inventory' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
            'procurement' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
            'feed-mill' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
            'semen' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
        ],
        'Breeding Manager' => [
            self::FARM => [A::View], self::MASTER => [A::View], 'settings' => [A::View],
            'animals' => [A::View, A::Create, A::Edit, A::Export, A::Print],
            'breeding' => [A::View, A::Create, A::Edit, A::Export, A::Print],
            'health' => [A::View, A::Create],
            'biosecurity' => [A::View, A::Create],
            'production' => [A::View],
            'semen' => [A::View, A::Create],
        ],
        'Veterinarian' => [
            self::FARM => [A::View], self::MASTER => [A::View], 'animals' => [A::View, A::Create], 'breeding' => [A::View, A::Create],
            'health' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
            'biosecurity' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
            'production' => [A::View],
            'semen' => [A::View],
        ],
        'Semen Laboratory Manager' => [self::FARM => [A::View], self::MASTER => [A::View], 'animals' => [A::View], 'breeding' => [A::View], 'health' => [A::View], 'biosecurity' => [A::View, A::Create], 'inventory' => [A::View, A::Create, A::Edit, A::Export, A::Print], 'semen' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print]],
        'Feed Mill Manager' => [self::FARM => [A::View], self::MASTER => [A::View], 'production' => [A::View], 'inventory' => [A::View, A::Create, A::Edit, A::Export, A::Print], 'procurement' => [A::View, A::Create], 'feed-mill' => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print]],
        'Nutritionist' => [self::FARM => [A::View], self::MASTER => [A::View], 'inventory' => [A::View], 'feed-mill' => [A::View, A::Create, A::Edit, A::Export, A::Print]],
        'Store Officer' => [self::FARM => [A::View], self::MASTER => [A::View], 'biosecurity' => [A::View, A::Create], 'production' => [A::View], 'inventory' => [A::View, A::Create, A::Edit, A::Export, A::Print], 'procurement' => [A::View, A::Create, A::Edit, A::Export, A::Print], 'feed-mill' => [A::View], 'semen' => [A::View]],
        'Sales Officer' => [self::FARM => [A::View], self::MASTER => [A::View], self::PRICES => [A::View], 'animals' => [A::View], 'health' => [A::View], 'production' => [A::View], 'inventory' => [A::View], 'semen' => [A::View]],
        'Slaughter Manager' => [self::FARM => [A::View], self::MASTER => [A::View], 'animals' => [A::View], 'health' => [A::View], 'biosecurity' => [A::View], 'production' => [A::View]],
        'Accountant' => [
            self::FARM => [A::View], self::MASTER => [A::View], 'animals' => [A::View],
            self::PRICES => [A::View, A::Create, A::Edit, A::Approve, A::Export, A::Print],
            'production' => [A::View, A::Export],
            'inventory' => [A::View, A::Export],
            'procurement' => [A::View, A::Create, A::Edit, A::Export, A::Print],
            'feed-mill' => [A::View, A::Export],
            'semen' => [A::View, A::Export],
        ],
        'Farm Worker' => [self::FARM => [A::View], self::MASTER => [A::View], 'animals' => [A::View, A::Create], 'breeding' => [A::View, A::Create], 'health' => [A::View, A::Create], 'biosecurity' => [A::View, A::Create], 'production' => [A::View, A::Create], 'inventory' => [A::View, A::Create], 'semen' => [A::View, A::Create]],
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
