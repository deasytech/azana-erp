<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_centres', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // The chart of accounts. system_key names the accounts the operational postings use.
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('type', 10)->index();
            $table->string('system_key', 40)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // A balanced set of lines. Posted entries never change; a mistake is reversed by a new entry.
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->date('entry_date')->index();
            $table->string('description');
            $table->string('status', 10)->default('posted')->index();
            $table->string('source_key', 60)->nullable()->unique();   // the operational document it came from, e.g. invoice:12
            $table->foreignId('reverses_id')->nullable()->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->unsignedBigInteger('total_minor');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('cost_centre_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('debit_minor')->default(0);
            $table->unsignedBigInteger('credit_minor')->default(0);
            $table->string('description')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['account_id', 'journal_entry_id']);
        });

        Schema::create('expense_records', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->date('expense_date')->index();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('cost_centre_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->foreignId('paid_from_account_id')->nullable()->constrained('accounts')->restrictOnDelete(); // null = not paid yet (accrued)
            $table->string('payee')->nullable();
            $table->string('reference', 60)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->date('transaction_date')->index();
            $table->string('direction', 3);
            $table->foreignId('cash_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('counter_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('cost_centre_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('reference', 60)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('fiscal_year')->index();
            $table->string('status', 10)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['fiscal_year', 'name']);
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('cost_centre_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();

            $table->index(['budget_id', 'account_id']);
        });
    }

    public function down(): void
    {
        foreach (['budget_lines', 'budgets', 'cash_transactions', 'expense_records', 'journal_lines', 'journal_entries', 'accounts', 'cost_centres'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
