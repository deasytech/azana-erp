<?php

namespace Database\Seeders;

use App\Enums\Module;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/** Idempotent: creates one permission per module action. */
class PermissionSeeder extends Seeder
{
    /**
     * @return list<string> names of permissions created by this run
     */
    public function createPermissions(): array
    {
        $created = [];

        foreach (Module::cases() as $module) {
            foreach ($module->actions() as $action) {
                $permission = Permission::findOrCreate($module->permission($action), 'web');

                if ($permission->wasRecentlyCreated) {
                    $created[] = $permission->name;
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $created;
    }

    public function run(): void
    {
        $this->createPermissions();
    }
}
