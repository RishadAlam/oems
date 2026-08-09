<?php

declare(strict_types=1);

namespace OEMS\App\Support;

final class Money
{
    public static function normalize(mixed $amount): ?string
    {
        if (is_int($amount)) {
            if ($amount < 0) {
                return null;
            }

            return $amount . '.00';
        }

        if (!is_string($amount)) {
            return null;
        }

        $amount = trim($amount);
        if (preg_match('/^\+?([0-9]+)(?:\.([0-9]{1,2}))?$/', $amount, $matches) !== 1) {
            return null;
        }

        $whole = ltrim($matches[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        $cents = str_pad($matches[2] ?? '', 2, '0');

        return $whole . '.' . $cents;
    }

    public static function minorUnits(mixed $amount): ?int
    {
        $normalized = self::normalize($amount);
        if ($normalized === null) {
            return null;
        }

        [$whole, $cents] = explode('.', $normalized, 2);
        $maximumWhole = intdiv(PHP_INT_MAX - 99, 100);
        $maximumWholeString = (string) $maximumWhole;

        if (strlen($whole) > strlen($maximumWholeString)
            || (strlen($whole) === strlen($maximumWholeString) && strcmp($whole, $maximumWholeString) > 0)) {
            return null;
        }

        return ((int) $whole * 100) + (int) $cents;
    }

    public static function isFree(mixed $amount): bool
    {
        return self::minorUnits($amount) === 0;
    }

    public static function format(mixed $amount, string $currency): string
    {
        $normalized = self::normalize($amount) ?? '0.00';
        [$whole, $cents] = explode('.', $normalized, 2);
        $formatted = self::groupThousands($whole) . ($cents === '00' ? '' : '.' . $cents);

        return match (strtoupper(trim($currency))) {
            'BDT' => '৳' . $formatted,
            'USD' => '$' . $formatted,
            default => $formatted . ' ' . strtoupper(trim($currency)),
        };
    }

    private static function groupThousands(string $whole): string
    {
        $groups = [];

        while (strlen($whole) > 3) {
            array_unshift($groups, substr($whole, -3));
            $whole = substr($whole, 0, -3);
        }

        array_unshift($groups, $whole);

        return implode(',', $groups);
    }
}
