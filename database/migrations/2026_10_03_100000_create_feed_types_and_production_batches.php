<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // A grower/finisher group managed together. Head counts come from the batch event ledger.
        Schema::create('production_batches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->foreignId('stage_id')->constrained('lookup_values')->restrictOnDelete();
            $table->foreignId('breed_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('pen_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('started_on')->index();
            $table->unsignedSmallInteger('placed_age_days')->nullable(); // average age when the batch started
            $table->decimal('target_weight_kg', 8, 2)->nullable();       // overrides the farm's market weight
            $table->string('status', 12)->default('active')->index();
            $table->date('closed_on')->nullable();
            $table->string('source_note')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        // Append-only head-count ledger: current heads = sum(delta).
        Schema::create('production_batch_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_batch_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->integer('delta');
            $table->date('occurred_on');
            $table->foreignId('animal_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cause_id')->nullable()->constrained('lookup_values')->restrictOnDelete();
            $table->unsignedBigInteger('unit_cost_minor')->nullable(); // per head, for placements
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['production_batch_id', 'occurred_on']);
        });

        // Individually tracked animals that belong to a batch.
        Schema::create('production_batch_animals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('animal_id')->constrained()->restrictOnDelete();
            $table->date('joined_on');
            $table->date('left_on')->nullable();
            $table->string('left_reason', 30)->nullable();
            $table->timestamps();

            $table->index(['animal_id', 'left_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_batch_animals');
        Schema::dropIfExists('production_batch_events');
        Schema::dropIfExists('production_batches');
        Schema::dropIfExists('feed_types');
    }
};
