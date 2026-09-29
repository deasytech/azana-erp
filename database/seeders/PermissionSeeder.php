<?php

namespace Database\Seeders;

use App\Enums\Module;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/** Idempotent: creates one permission per module action. */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Module::cases() as $module) {
            foreach ($module->actions() as $action) {
                Permission::findOrCreate($module->permission($action), 'web');
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
