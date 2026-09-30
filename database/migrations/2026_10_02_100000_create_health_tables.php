<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diseases', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_reportable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->foreignId('type_id')->constrained('lookup_values')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->restrictOnDelete();
            $table->unsignedSmallInteger('default_withdrawal_days')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Batch identity and expiry only: stock quantities belong to the Phase 08 inventory ledger.
        Schema::create('medicine_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->string('batch_number', 60);
            $table->date('expiry_date')->index();
            $table->date('received_on')->nullable();
            $table->string('supplier_name')->nullable();
            $table->decimal('quantity_received', 12, 3)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['medicine_id', 'batch_number']);
        });

        Schema::create('veterinary_visits', function (Blueprint $table) {
            $table->id();
            $table->date('visited_on')->index();
            $table->foreignId('veterinarian_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('veterinarian_name')->nullable();
            $table->string('reason');
            $table->text('findings')->nullable();
            $table->text('recommendations')->nullable();
            $table->date('follow_up_on')->nullable()->index();
            $table->unsignedBigInteger('cost_minor')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('health_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->restrictOnDelete();
            $table->foreignId('disease_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('veterinary_visit_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('severity', 10);
            $table->date('observed_on');
            $table->text('symptoms')->nullable();
            $table->string('status', 12)->default('open');
            $table->date('resolved_on')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index(['animal_id', 'status']);
        });

        Schema::create('treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->restrictOnDelete();
            $table->foreignId('health_event_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('veterinary_visit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('dose', 10, 3)->nullable();
            $table->string('dose_unit', 20)->nullable();
            $table->string('route', 20)->nullable();
            $table->date('administered_on');
            $table->unsignedSmallInteger('withdrawal_days')->default(0); // snapshot at the time of treatment
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['animal_id', 'administered_on']);
        });

        Schema::create('vaccination_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('lookup_values')->restrictOnDelete();
            $table->unsignedSmallInteger('first_dose_age_days');
            $table->unsignedSmallInteger('repeat_interval_days')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('vaccinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->restrictOnDelete();
            $table->foreignId('vaccination_schedule_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('administered_on');
            $table->decimal('dose', 10, 3)->nullable();
            $table->unsignedSmallInteger('withdrawal_days')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['animal_id', 'vaccination_schedule_id', 'administered_on'], 'vaccinations_due_lookup');
        });

        Schema::create('withdrawal_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->restrictOnDelete();
            $table->foreignId('treatment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('vaccination_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on'); // first day the animal may be sold or slaughtered
            $table->timestamp('cleared_at')->nullable();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('clear_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['animal_id', 'ends_on']);
        });

        Schema::create('laboratory_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('health_event_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('veterinary_visit_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('sample_type', 60);
            $table->string('test_name');
            $table->date('sampled_on');
            $table->date('resulted_on')->nullable();
            $table->text('result')->nullable();
            $table->boolean('is_abnormal')->default(false);
            $table->string('lab_name')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('quarantine_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->restrictOnDelete();
            $table->string('type', 12);
            $table->date('started_on');
            $table->text('reason');
            $table->foreignId('health_event_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('released_on')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('release_notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index(['animal_id', 'released_on']);
        });
    }

    public function down(): void
    {
        foreach (['quarantine_records', 'laboratory_results', 'withdrawal_periods', 'vaccinations', 'vaccination_schedules', 'treatments', 'health_events', 'veterinary_visits', 'medicine_batches', 'medicines', 'diseases'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
