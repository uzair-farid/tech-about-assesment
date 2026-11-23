<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('airline_attempts')) {
            return;
        }

        Schema::create('airline_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->string('idempotency_key', 64)->unique();
            $table->string('status', 15)->default('pending');
            $table->string('pnr', 20)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airline_attempts');
    }
};
