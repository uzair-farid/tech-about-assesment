<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('webhook_events')) {
            return;
        }

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 64)->unique();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->json('payload');
            $table->boolean('signature_valid');
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
