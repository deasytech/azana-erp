<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('breeds', function (Blueprint $table) {
            $table->id();
            $table->string('species', 30)->default('pig');
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('genetic_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('breed_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 60);
            $table->string('category', 20); // mass, volume, count, length, other
            $table->foreignId('base_unit_id')->nullable()->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('conversion_factor', 20, 8)->nullable(); // 1 of this unit = factor x base unit
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units_of_measure');
        Schema::dropIfExists('genetic_lines');
        Schema::dropIfExists('breeds');
    }
};
