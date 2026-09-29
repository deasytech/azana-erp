<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RoleSeeder::class, MasterDataSeeder::class]);

        // Demo accounts never exist in production; create the first owner with `php artisan erp:create-owner`.
        if (app()->environment('local', 'testing')) {
            foreach (array_keys(RoleSeeder::ROLES) as $role) {
                $slug = str($role)->slug();

                $email = "{$slug}@azana.test";

                // Idempotent: re-running the seeder must not fail on the unique email.
                $user = User::firstWhere('email', $email)
                    ?? User::factory()->create(['name' => $role, 'email' => $email]);

                $user->assignRole($role);
            }
        }
    }
}
