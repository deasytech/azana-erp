<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The server's record of every mutation a device has sent. client_id (made on the device) is unique: sending the same one again
        // never repeats the business transaction, it returns what happened the first time.
        Schema::create('sync_mutations', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_id')->unique();
            $table->string('device_id', 64)->index();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 40);
            $table->timestamp('occurred_at');                                  // when it happened on the device
            $table->json('payload');
            $table->string('status', 10)->index();                              // accepted | rejected | conflict | failed
            $table->string('error_code', 40)->nullable();
            $table->text('error_message')->nullable();
            $table->json('errors')->nullable();                                 // field by field, for rejected payloads
            $table->string('server_type', 40)->nullable();                      // what was created: weight, treatment, litter...
            $table->unsignedBigInteger('server_id')->nullable();
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->timestamp('attempted_at');
            $table->timestamp('synced_at')->nullable();                         // set once accepted
            $table->timestamp('reviewed_at')->nullable();                       // a supervisor looked at a conflict or rejection
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'status']);
            $table->index(['status', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_mutations');
    }
};
