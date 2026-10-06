<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The catalogue of what comes off a carcass. Each product is stocked as its own inventory item (in kg).
        Schema::create('meat_products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('kind', 14);
            $table->foreignId('inventory_item_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('shelf_life_days');           // use-by = production date + this
            $table->string('storage_note')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('slaughter_batches', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->date('scheduled_on')->index();
            $table->string('status', 12)->default('scheduled')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // One animal (or a group of untracked pigs from a batch) through intake, inspection and slaughter.
        Schema::create('slaughter_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slaughter_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('animal_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('production_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('heads')->default(1);
            $table->string('status', 12)->default('received')->index();
            $table->dateTime('received_at');
            $table->decimal('live_weight_kg', 9, 2);                   // total for the heads
            $table->unsignedBigInteger('live_cost_minor')->default(0); // what the pig(s) cost to raise, carried onto the meat
            $table->string('ante_mortem', 8);
            $table->text('ante_mortem_notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['animal_id', 'status']);
        });

        // Carcasses cut and boned into products; the products go into a cold room's stock.
        Schema::create('meat_production_batches', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->date('produced_on')->index();
            $table->foreignId('inventory_location_id')->constrained()->restrictOnDelete();
            $table->decimal('input_kg', 10, 2);
            $table->decimal('output_kg', 10, 2);
            $table->decimal('waste_kg', 10, 2)->default(0);
            $table->unsignedBigInteger('live_cost_minor');
            $table->unsignedBigInteger('other_cost_minor')->default(0);
            $table->unsignedBigInteger('total_cost_minor');
            $table->string('status', 10)->default('produced')->index();
            $table->uuid('group_uuid')->index();
            $table->text('notes')->nullable();
            $table->foreignId('produced_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reverse_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('carcasses', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('slaughter_record_id')->unique()->constrained()->restrictOnDelete();
            $table->dateTime('slaughtered_at');
            $table->decimal('live_weight_kg', 9, 2);
            $table->decimal('hot_weight_kg', 9, 2);
            $table->decimal('dressing_percent', 5, 2);
            $table->string('post_mortem', 9);
            $table->decimal('condemned_kg', 9, 2)->default(0);
            $table->text('post_mortem_notes')->nullable();
            $table->string('status', 10)->default('hanging')->index();
            $table->foreignId('meat_production_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('slaughtered_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // A corrected carcass weight, kept with who approved it and why.
        Schema::create('carcass_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carcass_id')->constrained()->restrictOnDelete();
            $table->decimal('old_hot_weight_kg', 9, 2);
            $table->decimal('new_hot_weight_kg', 9, 2);
            $table->text('reason');
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('meat_production_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meat_production_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('meat_product_id')->constrained()->restrictOnDelete();
            $table->decimal('weight_kg', 10, 2);
            $table->bigInteger('cost_minor');
            $table->date('use_by');
            $table->foreignId('inventory_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('inventory_transaction_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['meat_production_lines', 'carcass_adjustments', 'carcasses', 'meat_production_batches', 'slaughter_records', 'slaughter_batches', 'meat_products'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
