<?php

namespace App\Support;

final class BookingReference
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function generate(): string
    {
        $suffix = '';

        for ($i = 0; $i < 6; $i++) {
            $suffix .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return "TPK-{$suffix}";
    }
}
