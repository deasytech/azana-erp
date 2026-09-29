<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only lifecycle records: corrected by new rows (or voiding), never edited or deleted.
        Schema::create('animal_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->timestamp('changed_at');
            $table->text('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['animal_id', 'changed_at']);
        });

        Schema::create('animal_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_pen_id')->nullable()->constrained('pens')->restrictOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('to_pen_id')->nullable()->constrained('pens')->restrictOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('reason_id')->nullable()->constrained('lookup_values')->restrictOnDelete();
            $table->dateTime('moved_at');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['animal_id', 'moved_at']);
        });

        Schema::create('weight_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->restrictOnDelete();
            $table->decimal('weight_kg', 8, 2);
            $table->dateTime('weighed_at');
            $table->string('method', 20)->default('scale');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['animal_id', 'weighed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weight_records');
        Schema::dropIfExists('animal_movements');
        Schema::dropIfExists('animal_status_history');
    }
};
