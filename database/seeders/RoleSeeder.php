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

    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        foreach (self::ROLES as $name => $meta) {
            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['requires_two_factor' => $meta['2fa'], 'description' => $meta['desc']],
            );

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($this->permissionsFor($name));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Identity-module defaults only; operational modules grant their own
     * permissions to roles in the phase that introduces them.
     *
     * @return list<string>
     */
    private function permissionsFor(string $role): array
    {
        return match ($role) {
            Role::OWNER => $this->all(),
            'General Manager' => [
                Module::Users->permission(A::View),
                Module::AuditLogs->permission(A::View),
                Module::LoginActivity->permission(A::View),
            ],
            default => [],
        };
    }

    /** @return list<string> */
    private function all(): array
    {
        return collect(Module::cases())
            ->flatMap(fn (Module $m) => array_map(fn (A $a) => $m->permission($a), $m->actions()))
            ->all();
    }
}
