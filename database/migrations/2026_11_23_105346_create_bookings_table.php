<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bookings')) {
            return;
        }

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 10)->unique();
            $table->foreignId('fare_id')->constrained('fares')->restrictOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->restrictOnDelete();

            $table->string('status', 20)->default('hold');

            $table->unsignedBigInteger('base_fare_paisa');
            $table->unsignedBigInteger('tax_paisa');
            $table->unsignedBigInteger('total_paisa');
            $table->boolean('refundable');
            $table->json('fare_rules_snapshot');

            $table->string('passenger_name')->nullable();
            $table->string('supplier_mode', 10)->default('normal');

            $table->string('pnr', 20)->nullable();
            $table->unsignedBigInteger('refunded_paisa')->default(0);

            $table->dateTime('expires_at')->nullable();
            $table->dateTime('cancel_requested_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
