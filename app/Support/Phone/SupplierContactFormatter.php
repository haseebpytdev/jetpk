<?php

namespace App\Support\Phone;

use App\Models\Booking;

/**
 * Unified supplier-facing contact formatting for PIA NDC / Hitit Crane paths.
 *
 * Produces XML dialing parts plus CTCM/CTCB SSR and Contact Person free-text values
 * without duplicated country codes or trunk zeros (e.g. never "+92 0 +923…" or "92 0 923…").
 */
final class SupplierContactFormatter
{
    /**
     * @return array{
     *     country_code: string,
     *     national_number: string,
     *     e164: string,
     *     phone_country: string,
     *     phone_area: string,
     *     phone_number: string,
     *     ctcm_text: string,
     *     ctcb_text: string,
     *     ctcm_ssr_value: string,
     *     contact_person_phone: string,
     *     contact_person_text: string,
     *     valid: bool,
     *     audit: array{
     *         duplicate_country: bool,
     *         trunk_zero_after_country: bool,
     *         plus_inside_supplier_number: bool
     *     }
     * }
     */
    public static function format(string $phoneRaw, ?string $countryCode = null, ?string $areaCode = null): array
    {
        $raw = trim($phoneRaw);
        $countryHint = self::normalizeCountryHint($countryCode);

        $parts = PhoneNumberNormalizer::splitForSupplierDialing($raw, $countryHint);

        if ($countryHint !== null) {
            $parts = self::reconcileWithExplicitCountry($parts, $countryHint, $raw);
        }

        $country = $parts['phone_country'];
        $national = $parts['phone_number'];
        $area = self::normalizeAreaHint($parts['phone_area']);
        $e164 = $parts['e164'];
        $supplierDigits = $country.$national;
        $valid = (bool) ($parts['valid'] ?? false) && PhoneNumberNormalizer::isSupplierDialingShapeValid([
            'phone_country' => $country,
            'phone_area' => $area,
            'phone_number' => $national,
        ]);

        return [
            'country_code' => $country,
            'national_number' => $national,
            'e164' => $e164,
            'phone_country' => $country,
            'phone_area' => $area,
            'phone_number' => $national,
            'ctcm_text' => $national !== '' ? 'CTCM '.$supplierDigits : '',
            'ctcb_text' => $national !== '' ? 'CTCB '.$supplierDigits : '',
            'ctcm_ssr_value' => $supplierDigits,
            'contact_person_phone' => $e164,
            'contact_person_text' => $e164,
            'valid' => $valid,
            'audit' => self::auditFlags($raw, $countryCode, $areaCode, $country, $area, $national),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromBooking(Booking $booking): array
    {
        $booking->loadMissing('contact');
        $contact = $booking->contact;
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $contactMeta = is_array($contact?->meta) ? $contact->meta : [];

        $phoneRaw = trim((string) ($contact?->phone ?? $booking->contact_phone ?? ''));
        $countryCode = trim((string) (
            $contactMeta['phone_country_code']
            ?? $meta['phone_country_code']
            ?? $contact?->country
            ?? ''
        ));
        $areaCode = trim((string) (
            $contactMeta['phone_area_code']
            ?? $contactMeta['area_code']
            ?? $meta['phone_area_code']
            ?? ''
        ));

        return self::format(
            $phoneRaw,
            $countryCode !== '' ? $countryCode : null,
            $areaCode !== '' ? $areaCode : null,
        );
    }

    /**
     * PIA NDC / Hitit XML ContactInfoList phone block.
     *
     * @param  array<string, mixed>  $formatted
     * @return array{
     *     phone_country: string,
     *     phone_area: string,
     *     phone_number: string,
     *     ctcm_text: string,
     *     ctcb_text: string,
     *     contact_person_phone: string
     * }
     */
    public static function toXmlContact(array $formatted): array
    {
        return [
            'phone_country' => (string) ($formatted['phone_country'] ?? ''),
            'phone_area' => (string) ($formatted['phone_area'] ?? ''),
            'phone_number' => (string) ($formatted['phone_number'] ?? ''),
            'ctcm_text' => (string) ($formatted['ctcm_text'] ?? ''),
            'ctcb_text' => (string) ($formatted['ctcb_text'] ?? ''),
            'contact_person_phone' => (string) ($formatted['contact_person_phone'] ?? ''),
        ];
    }

    /**
     * Legacy concatenation patterns that produced bad PNR history (read-only preview for audits).
     *
     * @return array{
     *     contact_person: string,
     *     ctcm_ssr: string,
     *     ctcb_osi: string
     * }
     */
    public static function legacyBadPreviews(string $phoneRaw, ?string $countryCode, ?string $areaCode): array
    {
        $country = trim((string) ($countryCode ?? '92'));
        $countryDigits = preg_replace('/\D/', '', $country) ?? '92';
        $area = trim((string) ($areaCode ?? '0'));
        $phone = trim($phoneRaw);

        return [
            'contact_person' => trim('+'.$countryDigits.' '.$area.' '.$phone),
            'ctcm_ssr' => trim($countryDigits.' '.$area.' '.preg_replace('/^\+/', '', $phone)),
            'ctcb_osi' => trim($countryDigits.' '.$area.' '.$phone),
        ];
    }

    /**
     * @return array{
     *     duplicate_country: bool,
     *     trunk_zero_after_country: bool,
     *     plus_inside_supplier_number: bool
     * }
     */
    public static function auditFlags(
        string $phoneRaw,
        ?string $countryCode,
        ?string $areaCode,
        string $country,
        string $area,
        string $national,
    ): array {
        $raw = trim($phoneRaw);
        $countryDigits = preg_replace('/\D/', '', (string) ($countryCode ?? $country)) ?? '';
        $areaDigits = preg_replace('/\D/', '', (string) ($areaCode ?? '')) ?? '';

        $duplicateCountry = $national !== '' && $countryDigits !== ''
            && (str_starts_with($national, $countryDigits)
                || (str_contains($raw, '+'.$countryDigits) && str_contains($raw, '+'.$countryDigits.$national))
                || preg_match('/\+'.preg_quote($countryDigits, '/').'\s*\+/i', $raw) === 1);

        $trunkZero = ($area === '0' || $areaDigits === '0')
            || ($areaDigits !== '' && str_starts_with($national, '0'))
            || preg_match('/\b'.preg_quote($countryDigits, '/').'\s+0\s+/i', $raw) === 1;

        $plusInsideNational = str_contains($national, '+');

        return [
            'duplicate_country' => $duplicateCountry,
            'trunk_zero_after_country' => $trunkZero,
            'plus_inside_supplier_number' => $plusInsideNational,
        ];
    }

    /**
     * @param  array{
     *     phone_country: string,
     *     phone_area: string,
     *     phone_number: string,
     *     e164: string,
     *     valid: bool
     * }  $parts
     * @return array{
     *     phone_country: string,
     *     phone_area: string,
     *     phone_number: string,
     *     e164: string,
     *     valid: bool
     * }
     */
    private static function reconcileWithExplicitCountry(array $parts, string $countryHint, string $raw): array
    {
        $country = PhoneNumberNormalizer::normalizeCountryDigitsPublic($countryHint);
        if ($parts['phone_country'] === $country && PhoneNumberNormalizer::isSupplierDialingShapeValid($parts)) {
            return $parts;
        }

        return PhoneNumberNormalizer::splitForSupplierDialing($raw, $country);
    }

    private static function normalizeCountryHint(?string $countryCode): ?string
    {
        $hint = trim((string) $countryCode);

        return $hint !== '' ? $hint : null;
    }

    private static function normalizeAreaHint(?string $areaCode): string
    {
        $digits = preg_replace('/\D/', '', trim((string) $areaCode)) ?? '';
        if ($digits === '' || $digits === '0') {
            return '';
        }

        return $digits;
    }
}
