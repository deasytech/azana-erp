<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A meat sale line points at the lot it takes meat from: one product within one meat production batch.
        foreach (['sales_order_lines', 'invoice_lines'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('meat_production_line_id')->nullable()->constrained()->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['invoice_lines', 'sales_order_lines'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('meat_production_line_id');
            });
        }
    }
};
