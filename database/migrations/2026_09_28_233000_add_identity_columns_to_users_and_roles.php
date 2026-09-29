<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->index()->after('password');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
        });

        Schema::table(config('permission.table_names.roles'), function (Blueprint $table) {
            $table->boolean('requires_two_factor')->default(false);
            $table->string('description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names.roles'), function (Blueprint $table) {
            $table->dropColumn(['requires_two_factor', 'description']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_active', 'last_login_at', 'last_login_ip',
                'app_authentication_secret', 'app_authentication_recovery_codes',
            ]);
        });
    }
};
