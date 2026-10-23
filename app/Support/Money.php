<?php

namespace App\Support;

use NumberFormatter;

/**
 * Money is always stored as integer cents. This class turns what people
 * type ("12,50", "1.234,50", "12.5") into cents and back into "€ 12,50".
 */
final class Money
{
    public static function format(int $cents, string $currency = 'EUR', ?string $locale = null): string
    {
        $locale ??= app()->getLocale() === 'de' ? 'de_AT' : 'en_GB';
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($cents / 100, $currency);
    }

    /** Plain number for form fields, e.g. 1250 → "12,50". */
    public static function toInput(int $cents, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $value = number_format($cents / 100, 2, '.', '');

        return $locale === 'de' ? str_replace('.', ',', $value) : $value;
    }

    /** Cents, or null if the text is not a valid non-negative amount. */
    public static function parse(?string $input): ?int
    {
        $number = self::parseDecimal($input);

        return $number === null ? null : (int) round($number * 100);
    }

    /**
     * Accepts both decimal commas and points:
     * "12,50" "12.50" "1.234,50" "1,234.50" "1.234" (= 1234) "12".
     */
    public static function parseDecimal(?string $input): ?float
    {
        if ($input === null) {
            return null;
        }

        $text = preg_replace('/[^\d.,-]/u', '', $input);
        if ($text === '' || $text === null || str_contains($text, '-')) {
            return null;
        }

        $lastComma = strrpos($text, ',');
        $lastPoint = strrpos($text, '.');

        if ($lastComma !== false && $lastPoint !== false) {
            $decimal = $lastComma > $lastPoint ? ',' : '.';
            $thousands = $decimal === ',' ? '.' : ',';
            $text = str_replace($thousands, '', $text);
            $text = str_replace($decimal, '.', $text);
        } elseif ($lastComma !== false) {
            $text = str_replace(',', '.', $text);
        } elseif ($lastPoint !== false && substr_count($text, '.') === 1 && strlen($text) - $lastPoint - 1 === 3) {
            // "1.234" is one thousand two hundred thirty-four in German.
            $text = str_replace('.', '', $text);
        }

        if (! preg_match('/^\d+(\.\d+)?$/', $text)) {
            return null;
        }

        return (float) $text;
    }
}
