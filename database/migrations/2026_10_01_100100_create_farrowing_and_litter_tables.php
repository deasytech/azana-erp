<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farrowings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sow_id')->constrained('animals')->restrictOnDelete();
            $table->foreignId('breeding_service_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('farrowed_on');
            $table->unsignedSmallInteger('total_born');
            $table->unsignedSmallInteger('born_alive');
            $table->unsignedSmallInteger('stillborn')->default(0);
            $table->unsignedSmallInteger('mummified')->default(0);
            $table->decimal('total_birth_weight_kg', 8, 2)->nullable();
            $table->boolean('assisted')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['sow_id', 'farrowed_on']);
        });

        Schema::create('litters', function (Blueprint $table) {
            $table->id();
            $table->string('litter_number', 40)->unique();
            $table->foreignId('farrowing_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('sow_id')->constrained('animals')->restrictOnDelete();
            $table->foreignId('sire_id')->nullable()->constrained('animals')->restrictOnDelete();
            $table->foreignId('breeding_service_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('born_on')->index();
            $table->date('expected_weaning_on')->index();
            $table->string('status', 20)->default('suckling')->index();
            $table->date('weaned_on')->nullable();
            $table->timestamps();

            $table->index(['sow_id', 'born_on']);
        });

        Schema::create('piglets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('litter_id')->constrained()->restrictOnDelete();
            $table->foreignId('animal_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('birth_weight_kg', 6, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('litter_losses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('litter_id')->constrained()->restrictOnDelete();
            $table->date('occurred_on');
            $table->unsignedSmallInteger('count');
            $table->string('cause')->nullable();
            $table->foreignId('animal_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('weaning_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('litter_id')->unique()->constrained()->restrictOnDelete();
            $table->date('weaned_on');
            $table->unsignedSmallInteger('weaned_count');
            $table->decimal('total_weight_kg', 9, 2)->nullable();
            $table->date('expected_next_service_on');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('animal_parentage', function (Blueprint $table) {
            $table->foreignId('litter_id')->nullable()->after('dam_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('animal_parentage', function (Blueprint $table) {
            $table->dropConstrainedForeignId('litter_id');
        });
        Schema::dropIfExists('weaning_records');
        Schema::dropIfExists('litter_losses');
        Schema::dropIfExists('piglets');
        Schema::dropIfExists('litters');
        Schema::dropIfExists('farrowings');
    }
};
