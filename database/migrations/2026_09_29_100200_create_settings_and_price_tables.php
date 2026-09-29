<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->restrictOnDelete();
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['farm_id', 'key']);
        });

        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained('lookup_values')->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->char('currency_code', 3);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->restrictOnDelete();
            $table->string('code', 60);
            $table->string('description');
            $table->foreignId('unit_id')->constrained('units_of_measure')->restrictOnDelete();
            // Integer minor units (e.g. kobo): never floats for money.
            $table->unsignedBigInteger('unit_price_minor');
            $table->timestamps();

            $table->unique(['price_list_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('farm_settings');
    }
};
