<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Snapshots taken at death so analysis by pen, litter, sow, breed, age, month and stage stays true later.
        Schema::create('mortality_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->unique()->constrained()->restrictOnDelete();
            $table->date('died_on')->index();
            $table->foreignId('cause_id')->constrained('lookup_values')->restrictOnDelete();
            $table->foreignId('disease_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('age_days')->nullable();
            $table->foreignId('category_id')->constrained('lookup_values')->restrictOnDelete(); // production stage
            $table->foreignId('breed_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('pen_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('litter_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('sow_id')->nullable()->constrained('animals')->restrictOnDelete();
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('culling_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->unique()->constrained()->restrictOnDelete();
            $table->date('culled_on')->index();
            $table->foreignId('reason_id')->constrained('lookup_values')->restrictOnDelete();
            $table->decimal('weight_kg', 8, 2);
            $table->string('health_status', 20);
            $table->json('performance')->nullable(); // production performance at the time of culling
            $table->string('disposal', 20);
            $table->unsignedBigInteger('disposal_value_minor')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('culling_records');
        Schema::dropIfExists('mortality_records');
    }
};
