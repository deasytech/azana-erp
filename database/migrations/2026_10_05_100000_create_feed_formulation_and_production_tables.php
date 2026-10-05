<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A recipe for one feed type. Active formulas never change: a new version is made instead.
        Schema::create('feed_formulas', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30);
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('name');
            $table->foreignId('feed_type_id')->constrained()->restrictOnDelete();
            $table->string('status', 10)->default('draft')->index();
            $table->decimal('process_loss_percent', 5, 2)->default(0); // expected loss between mixing and bagging
            // Nutritional specification (targets, all optional).
            $table->decimal('crude_protein_percent', 5, 2)->nullable();
            $table->decimal('crude_fibre_percent', 5, 2)->nullable();
            $table->decimal('crude_fat_percent', 5, 2)->nullable();
            $table->decimal('calcium_percent', 5, 2)->nullable();
            $table->decimal('phosphorus_percent', 5, 2)->nullable();
            $table->decimal('lysine_percent', 5, 2)->nullable();
            $table->decimal('energy_kcal_per_kg', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['code', 'version']);
        });

        Schema::create('feed_formula_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_formula_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->decimal('inclusion_percent', 8, 4); // share of the mix; all items add up to 100
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['feed_formula_id', 'inventory_item_id']);
        });

        Schema::create('feed_production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique(); // FM-000123
            $table->foreignId('feed_formula_id')->constrained()->restrictOnDelete();
            $table->decimal('planned_output_kg', 12, 3);
            $table->date('planned_on')->index();
            $table->foreignId('source_location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->foreignId('output_location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->string('status', 10)->default('planned')->index();
            $table->text('notes')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // Materials planned from the formula when the order was made, and the amount production staff say was used.
        Schema::create('feed_production_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_production_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->decimal('planned_quantity', 14, 3);
            $table->decimal('actual_quantity', 14, 3)->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['feed_production_order_id', 'inventory_item_id'], 'feed_order_lines_unique');
        });

        // The finished feed batch and what it cost. Its ledger lines are found through group_uuid.
        Schema::create('feed_production_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_production_order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->restrictOnDelete();
            $table->decimal('output_kg', 12, 3);
            $table->date('produced_on')->index();
            $table->bigInteger('material_cost_minor');
            $table->bigInteger('other_cost_minor')->default(0);
            $table->bigInteger('total_cost_minor');
            $table->bigInteger('cost_per_kg_minor');
            $table->uuid('group_uuid')->index();
            $table->foreignId('produced_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reverse_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['feed_production_batches', 'feed_production_order_lines', 'feed_production_orders', 'feed_formula_items', 'feed_formulas'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
