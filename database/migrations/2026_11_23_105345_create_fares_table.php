<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fares')) {
            return;
        }

        Schema::create('fares', function (Blueprint $table) {
            $table->id();
            $table->string('origin', 3);
            $table->string('destination', 3);
            $table->string('carrier_code', 8);
            $table->string('flight_number', 10);
            $table->dateTime('departs_at');
            $table->dateTime('arrives_at');
            $table->unsignedBigInteger('base_fare_paisa');
            $table->unsignedBigInteger('tax_paisa');
            $table->string('currency', 3)->default('PKR');
            $table->unsignedInteger('seats_left');
            $table->boolean('refundable')->default(false);
            $table->json('fare_rules');
            $table->timestamps();

            $table->index(['origin', 'destination']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fares');
    }
};
