<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animals', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique(); // ULID: QR payloads and offline clients
            $table->string('animal_number', 40)->unique(); // permanent identification number
            $table->string('sex', 10);
            $table->foreignId('category_id')->constrained('lookup_values')->restrictOnDelete();
            $table->foreignId('breed_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('genetic_line_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('birth_date')->nullable()->index();
            $table->boolean('birth_date_estimated')->default(false);
            $table->string('source', 20);
            $table->date('acquired_on')->nullable();
            $table->string('source_name')->nullable();
            $table->string('source_reference')->nullable();
            $table->unsignedBigInteger('purchase_price_minor')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('status_changed_at')->nullable();
            $table->foreignId('current_pen_id')->nullable()->constrained('pens')->restrictOnDelete();
            $table->foreignId('current_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('animal_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->string('value', 100);
            $table->date('issued_on')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->string('retired_reason')->nullable();
            $table->timestamps();

            // Identifiers are never reused, even after retirement.
            $table->unique(['type', 'value']);
        });

        Schema::create('animal_parentage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('sire_id')->nullable()->constrained('animals')->restrictOnDelete();
            $table->foreignId('dam_id')->nullable()->constrained('animals')->restrictOnDelete();
            $table->string('sire_note')->nullable(); // unregistered / external parent
            $table->string('dam_note')->nullable();
            $table->timestamps();
        });

        Schema::create('animal_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->restrictOnDelete();
            $table->string('disk', 30);
            $table->string('path', 500);
            $table->string('caption')->nullable();
            $table->date('taken_on')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_photos');
        Schema::dropIfExists('animal_parentage');
        Schema::dropIfExists('animal_identifiers');
        Schema::dropIfExists('animals');
    }
};
