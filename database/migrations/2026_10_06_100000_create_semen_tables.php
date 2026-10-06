<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The boars in the semen programme (the animal itself stays in the animal registry).
        Schema::create('semen_boars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 10)->default('active')->index();
            $table->unsignedSmallInteger('min_interval_days')->nullable();      // overrides the farm setting
            $table->unsignedInteger('target_doses_per_week')->nullable();       // overrides the farm setting
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // The raw ejaculate. Append-only.
        Schema::create('semen_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->restrictOnDelete();
            $table->dateTime('collected_at')->index();
            $table->decimal('volume_ml', 8, 1);
            $table->string('colour', 30)->nullable();
            $table->string('odour', 30)->nullable();
            $table->decimal('ph', 3, 1)->nullable();
            $table->string('technician_name')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['animal_id', 'collected_at']);
        });

        // Every collection becomes one batch, followed from QC through release to the last dose.
        Schema::create('semen_batches', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();       // IPA-SM-DUR-20260904-001
            $table->foreignId('semen_collection_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('animal_id')->constrained()->restrictOnDelete();   // the boar
            $table->foreignId('breed_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('collected_on')->index();
            $table->date('expiry_date')->index();
            $table->string('status', 12)->default('pending_qc')->index();
            $table->unsignedInteger('doses_produced')->nullable();
            $table->decimal('dose_volume_ml', 6, 1)->nullable();
            $table->string('diluent')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('inventory_batch_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->string('quarantined_from', 12)->nullable();   // status to return to when cleared
            $table->text('status_reason')->nullable();            // why it is quarantined / destroyed
            $table->timestamps();

            $table->index(['animal_id', 'collected_on']);
        });

        Schema::create('semen_qc_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semen_batch_id')->constrained()->restrictOnDelete();
            $table->decimal('motility_percent', 5, 2);
            $table->decimal('concentration_million_per_ml', 8, 2);
            $table->decimal('abnormal_percent', 5, 2);
            $table->boolean('passed');
            $table->string('failed_because')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('evaluated_at');
            $table->timestamp('created_at')->useCurrent();
        });

        // One semen stock item per breed; a batch's doses are that item's stock, by batch and expiry.
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('breed_id')->nullable()->unique()->after('feed_type_id')->constrained()->restrictOnDelete();
        });

        Schema::table('breeding_services', function (Blueprint $table) {
            $table->foreignId('semen_batch_id')->nullable()->after('semen_source')->constrained()->restrictOnDelete();
        });

        Schema::table('price_list_items', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable()->after('unit_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('price_list_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_item_id');
        });
        Schema::table('breeding_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('semen_batch_id');
        });
        Schema::table('inventory_items', function (Blueprint $table) {
            // The foreign key goes first (MySQL will not drop an index a foreign key uses), then its unique index.
            $table->dropForeign(['breed_id']);
            $table->dropUnique(['breed_id']);
            $table->dropColumn('breed_id');
        });

        foreach (['semen_qc_records', 'semen_batches', 'semen_collections', 'semen_boars'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
