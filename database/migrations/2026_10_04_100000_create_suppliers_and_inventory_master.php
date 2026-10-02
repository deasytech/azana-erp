<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_number', 60)->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // A store, silo, cold room or pharmacy shelf where stock is kept; optionally tied to a farm location.
        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('category', 20)->index();
            $table->foreignId('unit_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->boolean('tracks_batches')->default(false);
            $table->boolean('tracks_expiry')->default(false);
            $table->decimal('reorder_level', 14, 3)->nullable();
            $table->decimal('reorder_quantity', 14, 3)->nullable();
            $table->foreignId('feed_type_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Batch/lot identity of received stock: expiry and supplier stay traceable.
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->string('batch_number', 60);
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('manufactured_on')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->date('received_on')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['inventory_item_id', 'batch_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_batches');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_locations');
        Schema::dropIfExists('suppliers');
    }
};
