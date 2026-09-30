<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biosecurity_visits', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_name');
            $table->string('organisation')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('vehicle_registration', 30)->nullable();
            $table->string('purpose');
            $table->dateTime('arrived_at')->index();
            $table->dateTime('departed_at')->nullable();
            $table->unsignedSmallInteger('last_pig_contact_hours')->nullable();
            $table->boolean('health_declaration')->default(false);
            $table->text('areas_visited')->nullable();
            $table->foreignId('host_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('host_name')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('biosecurity_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('description');
            $table->string('area', 60)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('biosecurity_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('checked_on')->index();
            $table->foreignId('performed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('items_total');
            $table->unsignedSmallInteger('items_passed');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('biosecurity_check_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biosecurity_check_id')->constrained()->restrictOnDelete();
            $table->foreignId('checklist_item_id')->nullable()->constrained('biosecurity_checklist_items')->restrictOnDelete();
            $table->string('description'); // snapshot of the checklist wording
            $table->boolean('passed');
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biosecurity_check_items');
        Schema::dropIfExists('biosecurity_checks');
        Schema::dropIfExists('biosecurity_checklist_items');
        Schema::dropIfExists('biosecurity_visits');
    }
};
