<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments')) {
            return;
        }

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->restrictOnDelete();

            $table->string('idempotency_key');
            $table->string('request_fingerprint', 64);

            $table->unsignedBigInteger('amount_paisa');
            $table->string('status', 15)->default('initiated');
            $table->string('psp_reference', 40)->nullable()->unique();
            $table->string('failure_reason')->nullable();

            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();

            $table->timestamps();

            $table->unique(['partner_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
