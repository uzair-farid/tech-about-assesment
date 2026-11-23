<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_parses_two_decimal_supplier_string(): void
    {
        $this->assertSame(8425050, Money::fromSupplierString('84250.50'));
    }

    public function test_parses_whole_rupee_amount(): void
    {
        $this->assertSame(10000000, Money::fromSupplierString('100000'));
    }

    public function test_pads_a_single_decimal_digit(): void
    {
        $this->assertSame(1000050, Money::fromSupplierString('10000.5'));
    }

    public function test_rejects_malformed_amounts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::fromSupplierString('not-a-number');
    }

    public function test_rejects_more_than_two_decimal_places(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::fromSupplierString('100.123');
    }

    public function test_round_trips_to_rupee_string(): void
    {
        $this->assertSame('84250.50', Money::toRupeeString(8425050));
    }
}
