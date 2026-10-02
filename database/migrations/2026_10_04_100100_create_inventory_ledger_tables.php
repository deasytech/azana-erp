<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The stock ledger: append-only. Quantity is signed (+ in, - out); value is in minor currency units.
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('group_uuid')->nullable()->index(); // rows posted together (a transfer's two legs, a multi-batch issue)
            $table->string('type', 20)->index();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->bigInteger('value_minor');
            $table->date('occurred_on')->index();
            $table->string('source_type', 30)->nullable(); // the document that caused it (goods receipt, stock count, ...)
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('reverses_id')->nullable()->unique()->constrained('inventory_transactions')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['inventory_item_id', 'inventory_location_id']);
            $table->index(['source_type', 'source_id']);
        });

        // Cost layers: what is left of each receipt, used to value issues. Changed only by the ledger actions.
        Schema::create('inventory_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('received_on');
            $table->decimal('quantity', 14, 3);
            $table->decimal('remaining_quantity', 14, 3);
            $table->bigInteger('remaining_value_minor');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['inventory_item_id', 'inventory_location_id', 'remaining_quantity'], 'inventory_layers_stock_index');
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('inventory_location_id')->constrained()->restrictOnDelete();
            $table->date('counted_on')->index();
            $table->string('status', 12)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('system_quantity', 14, 3);
            $table->decimal('counted_quantity', 14, 3)->nullable();
            $table->decimal('variance_quantity', 14, 3)->nullable();
            $table->bigInteger('variance_value_minor')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['stock_count_id', 'inventory_item_id']);
        });

        // A single-line manual correction (damage, found stock, ...) that needs approval before it moves stock.
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3); // signed
            $table->text('reason');
            $table->string('status', 10)->default('pending')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();
        });

        // Feed eaten can draw down stock; the ledger rows are found through this group id.
        Schema::table('feed_consumption_records', function (Blueprint $table) {
            $table->uuid('inventory_group')->nullable()->index()->after('cost_minor');
        });
    }

    public function down(): void
    {
        Schema::table('feed_consumption_records', function (Blueprint $table) {
            $table->dropIndex(['inventory_group']);
            $table->dropColumn('inventory_group');
        });
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('inventory_layers');
        Schema::dropIfExists('inventory_transactions');
    }
};
