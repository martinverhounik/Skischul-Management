<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Checks an IBAN with the international mod-97 check digits, so typing
 * mistakes are caught before a payment run fails.
 */
class Iban implements ValidationRule
{
    public static function normalize(string $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', $value));
    }

    public static function isValid(string $value): bool
    {
        $iban = self::normalize($value);

        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        // mod 97 on a long number, piece by piece
        $remainder = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) (($remainder.$chunk) % 97);
        }

        return $remainder === 1;
    }

    /** "AT611904300234573201" → "AT61 1904 3002 3457 3201" */
    public static function format(string $value): string
    {
        return trim(chunk_split(self::normalize($value), 4, ' '));
    }

    /** Only the country, check digits and last four characters. */
    public static function mask(string $value): string
    {
        $iban = self::normalize($value);

        return substr($iban, 0, 4).' •••• •••• '.substr($iban, -4);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail(__('This is not a valid IBAN. Please check it.'));
        }
    }
}
