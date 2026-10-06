<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->foreignId('customer_type_id')->constrained('lookup_values')->restrictOnDelete();
            $table->string('contact_name')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_number', 60)->nullable();
            // Credit: nothing is bought on credit until a limit has been approved.
            $table->unsignedBigInteger('credit_limit_minor')->default(0);
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->string('credit_status', 10)->default('none')->index();
            $table->foreignId('credit_approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('credit_approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('status', 12)->default('draft')->index();
            $table->date('ordered_on')->index();
            $table->char('currency_code', 3);
            $table->unsignedBigInteger('total_minor');
            $table->text('notes')->nullable();
            $table->text('credit_warning')->nullable();       // set when confirmed over the limit under the "warn" rule
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->date('dispatched_on')->nullable();
            $table->string('dispatch_note', 120)->nullable(); // delivery note / vehicle / driver
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamps();
        });

        // One line sells semen doses, a tracked pig, or untracked pigs from a production batch. Price is a snapshot.
        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->restrictOnDelete();
            $table->string('kind', 12);
            $table->string('description');
            $table->string('unit', 8);                                  // dose | head | kg
            $table->decimal('quantity', 12, 3);
            $table->unsignedInteger('heads')->nullable();               // pigs leaving (tracked animal = 1)
            $table->unsignedBigInteger('unit_price_minor');
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->unsignedBigInteger('line_total_minor');             // after discount
            $table->foreignId('semen_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('inventory_location_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('animal_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('production_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        // Stock or animals held for a confirmed order so they cannot be sold twice.
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('inventory_location_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('animal_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('production_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->string('status', 10)->default('active')->index();
            $table->timestamps();

            $table->index(['inventory_batch_id', 'inventory_location_id', 'status'], 'stock_reservations_stock_index');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_order_id')->unique()->constrained()->restrictOnDelete();
            $table->date('issued_on')->index();
            $table->date('due_on')->index();
            $table->char('currency_code', 3);
            $table->unsignedBigInteger('total_minor');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_order_line_id')->constrained()->restrictOnDelete();
            $table->string('kind', 12);
            $table->string('description');
            $table->string('unit', 8);
            $table->decimal('quantity', 12, 3);
            $table->unsignedBigInteger('unit_price_minor');
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->unsignedBigInteger('line_total_minor');
            $table->foreignId('semen_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('animal_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('production_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        // Money received. What is not allocated to an invoice stays with the customer as a deposit.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('method', 14);
            $table->date('received_on')->index();
            $table->string('reference', 60)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['payment_allocations', 'payments', 'invoice_lines', 'invoices', 'stock_reservations', 'sales_order_lines', 'sales_orders', 'customers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
