<?php

declare(strict_types=1);

namespace App\Support;

final class Money
{
    public static function add(string|int|float ...$values): string
    {
        $total = '0.00';
        foreach ($values as $value) {
            $total = bcadd($total, self::format($value), 2);
        }

        return self::format($total);
    }

    public static function subtract(string|int|float $left, string|int|float $right): string
    {
        return self::format(bcsub(self::format($left), self::format($right), 2));
    }

    public static function multiply(string|int|float $value, float $factor): string
    {
        return self::format(bcmul(self::format($value), (string) $factor, 2));
    }

    public static function format(string|int|float $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
