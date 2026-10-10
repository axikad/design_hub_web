<?php

namespace App\Support;

final class PhoneNormalizer
{
    public static function key(?string $countryCode, ?string $mobileNumber): ?string
    {
        if ($countryCode === null || $mobileNumber === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $mobileNumber) ?? '';
        $code = preg_replace('/\D+/', '', $countryCode) ?? '';

        if ($digits === '' || $code === '') {
            return null;
        }

        return '+'.$code.$digits;
    }
}
