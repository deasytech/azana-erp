<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Small admin-editable value lists (unit types, building types, pen purposes...).
        Schema::create('lookup_values', function (Blueprint $table) {
            $table->id();
            $table->string('category', 40);
            $table->string('code', 60);
            $table->string('name', 120);
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['category', 'code']);
        });

        Schema::create('farms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('timezone', 64)->default('Africa/Lagos');
            $table->char('currency_code', 3)->default('NGN');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('production_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->restrictOnDelete();
            $table->foreignId('type_id')->constrained('lookup_values')->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('type_id')->constrained('lookup_values')->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Target for composite foreign keys that keep children inside the same parent.
            $table->unique(['id', 'production_unit_id']);
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['id', 'building_id']);
        });

        // Non-pen places (stores, cold rooms, quarantine, loading bays...). Pens are their own table.
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_unit_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('building_id')->nullable();
            $table->unsignedBigInteger('room_id')->nullable();
            $table->foreignId('type_id')->constrained('lookup_values')->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign(['building_id', 'production_unit_id'])
                ->references(['id', 'production_unit_id'])->on('buildings')->restrictOnDelete();
            $table->foreign(['room_id', 'building_id'])
                ->references(['id', 'building_id'])->on('rooms')->restrictOnDelete();
        });

        Schema::create('pens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('room_id')->nullable();
            $table->foreignId('purpose_id')->constrained('lookup_values')->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // A pen's room must be in the pen's own building.
            $table->foreign(['room_id', 'building_id'])
                ->references(['id', 'building_id'])->on('rooms')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pens');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('buildings');
        Schema::dropIfExists('production_units');
        Schema::dropIfExists('farms');
        Schema::dropIfExists('lookup_values');
    }
};
