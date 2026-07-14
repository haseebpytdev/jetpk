<?php

namespace App\Support\Phone;

/**
 * Supplier-safe phone normalization for NDC/XML payloads (CountryDialingCode + PhoneNumber).
 * Customer-facing display formatting is unchanged — use only before supplier API calls.
 */
final class PhoneNumberNormalizer
{
    /**
     * Split a phone string into supplier dialing parts (no duplicated country code or leading national zero).
     *
     * @return array{
     *     phone_country: string,
     *     phone_area: string,
     *     phone_number: string,
     *     e164: string,
     *     valid: bool
     * }
     */
    public static function splitForSupplierDialing(string $phone, ?string $countryCode = null): array
    {
        $defaultCountry = self::normalizeCountryDigits($countryCode ?? '92');
        $raw = trim($phone);
        if ($raw === '') {
            return self::emptyResult($defaultCountry);
        }

        $compact = preg_replace('/[\s\-\(\)\.]/', '', $raw) ?? '';
        if (str_starts_with($compact, '00')) {
            $compact = '+'.substr($compact, 2);
        }

        $digits = preg_replace('/\D/', '', $compact) ?? '';
        if ($digits === '') {
            return self::emptyResult($defaultCountry);
        }

        if (str_starts_with($compact, '+') || strlen($digits) > 11) {
            return self::parseInternationalDigits($digits, $defaultCountry);
        }

        if ($countryCode !== null && trim($countryCode) !== '') {
            $country = self::normalizeCountryDigits($countryCode);

            if (str_starts_with($compact, '+') || str_starts_with($compact, '00')) {
                return self::parseInternationalDigits($digits, $country);
            }

            if (str_starts_with($digits, $country) && strlen($digits) > strlen($country)) {
                $national = self::stripNationalLeadingZero(substr($digits, strlen($country)));

                return self::buildResult($country, '', $national);
            }

            return self::buildResult($country, '', self::stripNationalLeadingZero($digits));
        }

        if ($defaultCountry === '92') {
            if (str_starts_with($digits, '92')) {
                return self::parsePakistanDigits($digits);
            }
            if (str_starts_with($digits, '0')) {
                return self::buildResult('92', '', self::stripNationalLeadingZero($digits));
            }
            if (strlen($digits) === 10 && str_starts_with($digits, '3')) {
                return self::buildResult('92', '', $digits);
            }
        }

        return self::buildResult($defaultCountry, '', self::stripNationalLeadingZero($digits));
    }

    /**
     * @param  array{phone_country?: string, phone_area?: string, phone_number?: string}  $parts
     */
    public static function isSupplierDialingShapeValid(array $parts): bool
    {
        $country = trim((string) ($parts['phone_country'] ?? ''));
        $number = trim((string) ($parts['phone_number'] ?? ''));
        if ($country === '' || $number === '') {
            return false;
        }
        if (str_contains($country, '+') || str_starts_with($number, '+')) {
            return false;
        }
        if (str_starts_with($number, '92')) {
            return false;
        }
        if ($country === '92' && str_starts_with($number, '0')) {
            return false;
        }

        return true;
    }

    /**
     * @return array{
     *     phone_country: string,
     *     phone_area: string,
     *     phone_number: string,
     *     e164: string,
     *     valid: bool
     * }
     */
    private static function parseInternationalDigits(string $digits, string $fallbackCountry): array
    {
        if (str_starts_with($digits, '92')) {
            return self::parsePakistanDigits($digits);
        }

        if (strlen($digits) > 10) {
            $country = substr($digits, 0, strlen($digits) - 10);
            $national = self::stripNationalLeadingZero(substr($digits, -10));

            return self::buildResult($country, '', $national);
        }

        return self::buildResult($fallbackCountry, '', self::stripNationalLeadingZero($digits));
    }

    /**
     * @return array{
     *     phone_country: string,
     *     phone_area: string,
     *     phone_number: string,
     *     e164: string,
     *     valid: bool
     * }
     */
    private static function parsePakistanDigits(string $digits): array
    {
        $rest = substr($digits, 2);
        $rest = self::stripNationalLeadingZero($rest);

        return self::buildResult('92', '', $rest);
    }

    private static function stripNationalLeadingZero(string $digits): string
    {
        return ltrim($digits, '0');
    }

    private static function normalizeCountryDigits(string $code): string
    {
        $digits = preg_replace('/\D/', '', $code) ?? '';

        return $digits !== '' ? $digits : '92';
    }

    public static function normalizeCountryDigitsPublic(string $code): string
    {
        return self::normalizeCountryDigits($code);
    }

    public static function defaultCountryDigits(): string
    {
        return '92';
    }

    /**
     * @return array{
     *     phone_country: string,
     *     phone_area: string,
     *     phone_number: string,
     *     e164: string,
     *     valid: bool
     * }
     */
    private static function buildResult(string $country, string $area, string $national): array
    {
        $country = self::normalizeCountryDigits($country);
        $national = self::stripNationalLeadingZero($national);
        $e164 = $national !== '' ? '+'.$country.$national : '';
        $valid = $national !== '' && strlen($national) >= 7;

        return [
            'phone_country' => $country,
            'phone_area' => $area,
            'phone_number' => $national,
            'e164' => $e164,
            'valid' => $valid,
        ];
    }

    /**
     * @return array{
     *     phone_country: string,
     *     phone_area: string,
     *     phone_number: string,
     *     e164: string,
     *     valid: bool
     * }
     */
    private static function emptyResult(string $country): array
    {
        return [
            'phone_country' => self::normalizeCountryDigits($country),
            'phone_area' => '',
            'phone_number' => '',
            'e164' => '',
            'valid' => false,
        ];
    }
}
