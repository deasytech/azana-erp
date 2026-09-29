<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;

class CreateOwner extends Command
{
    protected $signature = 'erp:create-owner {name} {email}';

    protected $description = 'Create an Owner/Director account (seeds roles and permissions if missing)';

    public function handle(): int
    {
        $this->callSilently('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);

        $password = password('Password', validate: fn (string $v) => Validator::make(['p' => $v], ['p' => Password::defaults()])->errors()->first('p') ?: null);

        $validator = Validator::make(
            ['name' => $this->argument('name'), 'email' => $this->argument('email')],
            ['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email'],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        User::create([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => $password,
            'email_verified_at' => now(),
        ])->assignRole('Owner/Director');

        $this->info('Owner created. They will be asked to set up two-factor authentication at first login.');

        return self::SUCCESS;
    }
}
