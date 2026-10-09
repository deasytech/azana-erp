<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One uploaded file. It is checked first (nothing is saved) and committed only when every row passed.
        Schema::create('data_imports', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30)->index();
            $table->string('original_name');
            $table->string('path');
            $table->string('status', 12)->default('checked')->index();   // checked | committing | committed
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('committed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('data_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('data');
            $table->text('error')->nullable();
            $table->unique(['data_import_id', 'row_number']);
        });

        // Every backup and restore test, so "is it working" is a query and not a hope.
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 12)->index();                          // backup | restore_test
            $table->string('status', 10)->index();                        // success | failed
            $table->string('file')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->boolean('offsite')->default(false);
            $table->text('message')->nullable();
            $table->timestamp('pruned_at')->nullable();                   // the files were removed by the retention rule
            $table->foreignId('backup_run_id')->nullable()->constrained('backup_runs')->restrictOnDelete();   // restore test -> the backup it proved
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
        });

        // Imported history is flagged so it never reaches the ledger twice.
        foreach (['sales_orders', 'invoices', 'payments'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->boolean('is_historical')->default(false)->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['sales_orders', 'invoices', 'payments'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropIndex($name.'_is_historical_index');
                $table->dropColumn('is_historical');
            });
        }

        foreach (['backup_runs', 'data_import_rows', 'data_imports'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
