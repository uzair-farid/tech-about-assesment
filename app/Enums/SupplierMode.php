<?php

namespace App\Enums;

/**
 * Lets the API caller force the fake airline supplier's behaviour for a
 * given booking, so failure paths (slow supplier, flaky supplier) can be
 * exercised deterministically in demos and tests. Not something a real
 * supplier integration would expose.
 */
enum SupplierMode: string
{
    case Normal = 'normal';
    case Slow = 'slow';
    case Flaky = 'flaky';
}
