<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');   // for SMS / WhatsApp alerts
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 12)->index();
            $table->string('priority', 8)->default('normal');
            $table->string('status', 12)->default('open')->index();
            $table->date('due_on')->index();
            $table->string('responsible_role')->nullable();                 // who may pick it up while nobody is assigned
            $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('source_key', 120)->nullable()->unique();        // the alert or schedule it came from: generating twice makes one
            $table->boolean('requires_evidence')->default(false);
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_notes')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['assigned_to', 'status']);
        });

        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('task_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->restrictOnDelete();
            $table->string('path');
            $table->string('caption')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'task_evidence', 'task_assignments', 'tasks'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('phone'));
    }
};
