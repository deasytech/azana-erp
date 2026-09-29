<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heat_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sow_id')->constrained('animals')->restrictOnDelete();
            $table->date('detected_on');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sow_id', 'detected_on']);
        });

        Schema::create('breeding_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sow_id')->constrained('animals')->restrictOnDelete();
            $table->foreignId('boar_id')->nullable()->constrained('animals')->restrictOnDelete();
            $table->string('method', 30);
            $table->date('serviced_on');
            $table->string('semen_source')->nullable(); // until semen batches exist (Phase 10)
            $table->foreignId('technician_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('technician_name')->nullable();
            // Snapshot of the farm's settings at the time of service, so later setting changes never rewrite history.
            $table->date('expected_pregnancy_check_on');
            $table->date('expected_farrowing_on')->index();
            $table->date('expected_weaning_on');
            $table->date('expected_next_heat_on');
            $table->date('expected_next_service_on');
            $table->string('outcome', 20)->default('pending')->index();
            $table->date('outcome_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['sow_id', 'serviced_on']);
        });

        Schema::create('pregnancy_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('breeding_service_id')->constrained()->restrictOnDelete();
            $table->date('checked_on');
            $table->string('result', 10);
            $table->string('method', 20);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pregnancy_checks');
        Schema::dropIfExists('breeding_services');
        Schema::dropIfExists('heat_events');
    }
};
