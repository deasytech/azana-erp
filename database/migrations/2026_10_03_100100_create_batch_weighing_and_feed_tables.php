<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sample weigh-ins: average weight of `sample_size` pigs. Wrong ones are voided, never edited.
        Schema::create('batch_weigh_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_batch_id')->constrained()->restrictOnDelete();
            $table->date('weighed_on');
            $table->unsignedSmallInteger('sample_size');
            $table->decimal('average_weight_kg', 8, 2);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['production_batch_id', 'weighed_on']);
        });

        // Feed eaten by a batch or by one animal. No stock is posted here: the inventory ledger is Phase 08.
        Schema::create('feed_consumption_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('animal_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('feed_type_id')->constrained()->restrictOnDelete();
            $table->date('consumed_on');
            $table->decimal('quantity_kg', 10, 2);
            $table->unsignedBigInteger('cost_per_kg_minor')->nullable(); // snapshot; Phase 09 derives it from feed batches
            $table->unsignedBigInteger('cost_minor')->nullable();        // quantity x cost per kg, rounded to a minor unit
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['production_batch_id', 'consumed_on']);
            $table->index(['animal_id', 'consumed_on']);
        });

        Schema::create('production_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_batch_id')->constrained()->restrictOnDelete();
            $table->date('incurred_on');
            $table->string('category', 20);
            $table->unsignedBigInteger('amount_minor');
            $table->string('description');
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['production_batch_id', 'incurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_costs');
        Schema::dropIfExists('feed_consumption_records');
        Schema::dropIfExists('batch_weigh_ins');
    }
};
