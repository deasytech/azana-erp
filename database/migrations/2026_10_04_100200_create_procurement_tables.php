<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->string('status', 12)->default('draft')->index();
            $table->date('needed_by')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->unsignedBigInteger('estimated_unit_cost_minor')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->date('ordered_on')->index();
            $table->date('expected_on')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(0); // snapshot of the supplier's terms
            $table->char('currency_code', 3);
            $table->unsignedBigInteger('total_minor');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->unsignedBigInteger('unit_cost_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->timestamps();
        });

        // A delivery received against an order. Posting it adds the goods to stock; voiding it reverses that.
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->restrictOnDelete();
            $table->date('received_on')->index();
            $table->string('delivery_note', 60)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->unsignedBigInteger('unit_cost_minor');
            $table->unsignedBigInteger('value_minor');
            $table->foreignId('inventory_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('inventory_transaction_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number', 60);
            $table->date('invoice_date');
            $table->date('due_date')->index();
            $table->unsignedBigInteger('subtotal_minor'); // goods, matched against what was received
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['supplier_id', 'invoice_number']);
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('supplier_invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->date('paid_on')->index();
            $table->string('method', 14);
            $table->string('reference', 60)->nullable();
            $table->string('status', 16)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['supplier_payments', 'supplier_invoices', 'goods_receipt_lines', 'goods_receipts', 'purchase_order_lines', 'purchase_orders', 'purchase_request_lines', 'purchase_requests'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
