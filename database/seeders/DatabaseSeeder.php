<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // Demo accounts never exist in production; create the first owner with `php artisan erp:create-owner`.
        if (app()->environment('local', 'testing')) {
            foreach (array_keys(RoleSeeder::ROLES) as $role) {
                $slug = str($role)->slug();

                User::factory()->create([
                    'name' => $role,
                    'email' => "{$slug}@azana.test",
                ])->assignRole($role);
            }
        }
    }
}
