<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Management's targets: planning data, not code. A month's own row wins over the year's (month null).
        Schema::create('kpi_targets', function (Blueprint $table) {
            $table->id();
            $table->string('kpi_key', 60);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month')->nullable();
            // A unique index treats every NULL as different, so the whole-year row (month empty) is keyed as month 0 to stay unique too.
            $table->unsignedTinyInteger('period_key')->storedAs('coalesce(month, 0)');
            $table->decimal('target_value', 18, 4);
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['kpi_key', 'year', 'period_key'], 'kpi_targets_key_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_targets');
    }
};
