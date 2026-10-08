<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the public site offers. A listing only describes; its price is read from the price lists through the linked stock item.
        Schema::create('website_listings', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 12)->index();                                 // pigs | semen | meat | service
            $table->string('slug', 120)->unique();
            $table->string('title');
            $table->string('summary', 300);
            $table->text('description')->nullable();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('price_unit', 30)->nullable();                         // "per dose", "per kg": how the price list's price is quoted
            $table->boolean('show_price')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->boolean('is_published')->default(false)->index();
            $table->timestamps();
        });

        // A request from the public, to be followed up by staff. It reserves no stock and creates no order: staff turn it into
        // a customer and an order through the sales screens.
        Schema::create('website_enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();                               // ENQ-000001
            $table->string('kind', 12)->index();                                  // pigs | semen | meat | general
            $table->foreignId('listing_id')->nullable()->constrained('website_listings')->nullOnDelete();
            $table->string('name', 150);
            $table->string('organisation', 150)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('quantity', 100)->nullable();                          // as the visitor wrote it: "20 weaners", "50 doses"
            $table->text('message');
            $table->string('status', 12)->default('new')->index();               // new | contacted | quoted | converted | closed | spam
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('staff_note')->nullable();
            $table->string('source_ip_hash', 64)->nullable();                     // hashed: enough to spot a flood, not to identify a person
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_enquiries');
        Schema::dropIfExists('website_listings');
    }
};
