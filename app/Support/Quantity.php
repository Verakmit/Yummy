<?php

namespace App\Support;

class Quantity
{
    /**
     * Format a stored quantity the Spanish way, without trailing zeros: "0.50" becomes "0,5".
     */
    public static function format(string|float|int|null $quantity): string
    {
        if ($quantity === null || $quantity === '') {
            return '';
        }

        return rtrim(rtrim(number_format((float) $quantity, 2, ',', ''), '0'), ',');
    }

    /**
     * Turn what the user typed ("0,5") into something the numeric validation rule accepts.
     */
    public static function normalize(mixed $quantity): mixed
    {
        return is_string($quantity) ? str_replace(',', '.', trim($quantity)) : $quantity;
    }
}
