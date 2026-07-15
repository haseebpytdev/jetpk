<?php

namespace App\Support\Travel;

class TravelDocumentFormatter
{
    /**
     * Mask a passport or national ID for customer-facing surfaces (e.g. AB123•••).
     */
    public static function maskPassport(?string $number): ?string
    {
        if ($number === null) {
            return null;
        }

        $n = preg_replace('/\s+/u', '', $number) ?? '';
        if ($n === '') {
            return null;
        }

        $len = mb_strlen($n);
        if ($len <= 3) {
            return str_repeat('•', max(0, $len - 1)).mb_substr($n, -1);
        }

        $prefixLen = min(5, $len - 1);
        $prefix = mb_substr($n, 0, $prefixLen);

        return $prefix.'•••';
    }

    /**
     * Mask document number for traveler list surfaces (e.g. PK****1234).
     */
    public static function maskDocumentForList(?string $number): ?string
    {
        if ($number === null) {
            return null;
        }

        $n = preg_replace('/\s+/u', '', $number) ?? '';
        if ($n === '') {
            return null;
        }

        $len = mb_strlen($n);
        if ($len <= 4) {
            return str_repeat('*', max(0, $len - 1)).mb_substr($n, -1);
        }

        $prefixLen = min(2, $len - 4);
        $prefix = mb_substr($n, 0, $prefixLen);
        $suffix = mb_substr($n, -4);

        return $prefix.str_repeat('*', 4).$suffix;
    }

    public static function maskEmail(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        $email = trim($email);
        if (! str_contains($email, '@')) {
            return self::maskPassport($email);
        }

        [$local, $domain] = explode('@', $email, 2);
        $localLen = mb_strlen($local);
        $visible = $localLen <= 1 ? '*' : mb_substr($local, 0, 1).str_repeat('*', max(1, $localLen - 1));

        return $visible.'@'.$domain;
    }

    public static function maskPhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '***';
        }

        $len = strlen($digits);
        if ($len <= 4) {
            return str_repeat('*', max(0, $len - 1)).substr($digits, -1);
        }

        return substr($digits, 0, 4).str_repeat('*', max(2, $len - 7)).substr($digits, -3);
    }

    public static function maskPersonName(?string $title, ?string $firstName, ?string $lastName): string
    {
        $maskPart = static function (?string $part): string {
            $part = trim((string) $part);
            if ($part === '') {
                return '';
            }

            return mb_substr($part, 0, 1).str_repeat('*', max(2, mb_strlen($part) - 1));
        };

        return trim(implode(' ', array_filter([
            trim((string) $title),
            $maskPart($firstName),
            $maskPart($lastName),
        ])));
    }
}
