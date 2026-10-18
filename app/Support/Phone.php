<?php

namespace App\Support;

final class Phone
{
    /** "+880 1712-345678", "8801712345678", "017 1234 5678" -> "01712345678" */
    public static function normalize(string $input): string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        if (str_starts_with($digits, '880')) {
            $digits = '0' . substr($digits, 3);
        }

        return $digits;
    }

    /** Bangladesh mobile: 01[3-9] + 8 digits. */
    public static function isValid(string $number): bool
    {
        return (bool) preg_match('/^01[3-9]\d{8}$/', $number);
    }
}
