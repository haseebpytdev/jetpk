<?php

namespace App\Support\Emails;

use App\Models\Agency;
use App\Models\Booking;
use App\Support\Branding\BrandDisplayResolver;
use App\Support\Branding\CompanyEmailProfileResolver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Global placeholder variables merged into every operational/booking email render.
 */
class EmailBaseVariables
{
    private const NEUTRAL_BRAND_NAME = 'Travel Platform';

    /**
     * @return array<string, scalar|null>
     */
    public static function forContext(Agency $agency, ?Booking $booking = null): array
    {
        $profile = CompanyEmailProfileResolver::resolve($agency);
        $brandName = self::resolveBrandName($agency);

        $variables = [
            'brand_name' => $brandName,
            'brand_logo_url' => (string) ($profile->logo_url ?? ''),
            'brand_support_email' => (string) ($profile->support_email ?? config('mail.from.address', '')),
            'brand_phone' => (string) ($profile->support_phone ?? ''),
            'brand_website' => (string) ($profile->website_url ?? ''),
            'agency_name' => $brandName,
            'company_name' => $brandName,
            'support_email' => self::resolveSupportEmail($profile->support_email ?? null),
            'support_phone' => self::resolveSupportPhone($profile->support_phone ?? null),
            'support_url' => (string) ($profile->website_url ?? ''),
            'website_url' => (string) ($profile->website_url ?? ''),
            'booking_reference' => '',
            'booking_status' => '',
            'customer_name' => '',
            'customer_email' => '',
            'customer_phone' => '',
            'trip_type' => '',
            'route' => '',
            'travel_date' => '',
            'created_at' => '',
            'payment_status' => '',
            'fare_total' => '',
            'currency' => '',
            'manage_booking_url' => '',
            'passenger_name' => '',
        ];

        if ($booking !== null) {
            $booking->loadMissing(['contact', 'customer', 'fareBreakdown']);
            $variables = array_merge($variables, self::bookingVariables($booking, $brandName));
        }

        return self::applyAliases($variables);
    }

    /**
     * Safe JetPK sample/audit variables when no agency record is available.
     *
     * @return array<string, scalar|null>
     */
    public static function jetpkSampleVariables(): array
    {
        $brand = JetpkEmailBrandingResolver::resolve('jetpk');
        $brandName = trim((string) ($brand['brand_name'] ?? 'JetPakistan'));
        if ($brandName === '' || EmailPlaceholderFallbacks::isForbiddenBrandName($brandName)) {
            $brandName = 'JetPakistan';
        }

        $companyName = trim((string) ($brand['legal_name'] ?? $brandName));
        if ($companyName === '' || EmailPlaceholderFallbacks::isForbiddenBrandName($companyName)) {
            $companyName = $brandName;
        }

        return self::applyAliases([
            'brand_name' => $brandName,
            'agency_name' => $brandName,
            'company_name' => $companyName,
            'brand_logo_url' => (string) ($brand['logo_url'] ?? ''),
            'brand_support_email' => (string) ($brand['support_email'] ?? 'support@jetpakistan.com'),
            'brand_phone' => (string) ($brand['support_phone'] ?? '+92 21 111 000 000'),
            'support_email' => self::resolveSupportEmail($brand['support_email'] ?? null),
            'support_phone' => self::resolveSupportPhone($brand['support_phone'] ?? '+92 21 111 000 000'),
            'website_url' => (string) ($brand['home_url'] ?? ''),
        ]);
    }

    /**
     * @param  array<string, mixed>  $runtimeVariables
     * @return array<string, scalar|null>
     */
    public static function mergeWithoutAgency(array $runtimeVariables = []): array
    {
        return self::applyAliases(array_merge(
            self::jetpkSampleVariables(),
            self::normalizeScalars($runtimeVariables),
        ));
    }

    /**
     * @param  array<string, scalar|null>  $variables
     * @return array<string, scalar|null>
     */
    public static function merge(Agency $agency, ?Booking $booking, array $variables): array
    {
        return self::applyAliases(array_merge(
            self::forContext($agency, $booking),
            self::normalizeScalars($variables),
        ));
    }

    /**
     * @return array<string, scalar|null>
     */
    protected static function bookingVariables(Booking $booking, string $brandName): array
    {
        $contactName = trim((string) ($booking->contact?->meta['name'] ?? ''));
        if ($contactName === '') {
            $contactName = trim((string) ($booking->customer?->name ?? ''));
        }
        if ($contactName === '') {
            $contactName = 'Customer';
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $tripType = trim((string) (data_get($meta, 'itinerary_overview.trip_type_label') ?? ''));
        $fareTotal = $booking->fareBreakdown?->total;
        $currency = (string) ($booking->currency ?? $booking->fareBreakdown?->currency ?? 'PKR');

        return [
            'booking_reference' => (string) $booking->reference_code,
            'booking_status' => Str::headline(str_replace('_', ' ', (string) $booking->status->value)),
            'status' => (string) $booking->status->value,
            'customer_name' => $contactName,
            'passenger_name' => $contactName,
            'customer_email' => (string) ($booking->contact?->email ?? $booking->customer?->email ?? ''),
            'customer_phone' => (string) ($booking->contact?->phone ?? ''),
            'trip_type' => $tripType !== '' ? $tripType : 'One way',
            'route' => trim((string) ($booking->route ?? '')) !== '' ? (string) $booking->route : '',
            'travel_date' => $booking->travel_date?->format('d M Y') ?? '',
            'supplier_booking_status' => trim((string) ($booking->supplier_booking_status ?? '')),
            'supplier_status' => trim((string) ($booking->supplier_booking_status ?? '')),
            'created_at' => $booking->created_at?->format('d M Y, H:i') ?? '',
            'payment_status' => $booking->payment_status !== null
                ? Str::headline(str_replace('_', ' ', (string) $booking->payment_status))
                : '',
            'fare_total' => $fareTotal !== null && (float) $fareTotal > 0
                ? number_format((float) $fareTotal, 2)
                : '',
            'currency' => $currency,
            'amount' => $fareTotal !== null && (float) $fareTotal > 0
                ? number_format((float) $fareTotal, 2)
                : '',
            'manage_booking_url' => self::manageBookingUrl($booking),
            'admin_booking_url' => self::adminBookingUrl($booking),
            'pnr' => trim((string) ($booking->pnr ?? '')),
        ];
    }

    /**
     * @param  array<string, scalar|null>  $variables
     * @return array<string, scalar|null>
     */
    protected static function applyAliases(array $variables): array
    {
        if (trim((string) ($variables['brand_name'] ?? '')) === '' && trim((string) ($variables['agency_name'] ?? '')) !== '') {
            $variables['brand_name'] = (string) $variables['agency_name'];
        }

        if (trim((string) ($variables['booking_status'] ?? '')) === '') {
            $status = trim((string) ($variables['status'] ?? $variables['current_status'] ?? $variables['notification_status'] ?? ''));
            if ($status !== '') {
                $variables['booking_status'] = Str::headline(str_replace('_', ' ', $status));
            }
        }

        if (trim((string) ($variables['passenger_name'] ?? '')) === '' && trim((string) ($variables['customer_name'] ?? '')) !== '') {
            $variables['passenger_name'] = (string) $variables['customer_name'];
        }

        if (trim((string) ($variables['amount'] ?? '')) === '' && trim((string) ($variables['fare_total'] ?? '')) !== '') {
            $variables['amount'] = (string) $variables['fare_total'];
        }

        if (trim((string) ($variables['review_reason'] ?? '')) === '') {
            $reviewReason = trim((string) ($variables['staff_review_reason'] ?? $variables['manual_review_reason'] ?? ''));
            if ($reviewReason !== '') {
                $variables['review_reason'] = $reviewReason;
            }
        }

        if (trim((string) ($variables['supplier_status'] ?? '')) === '') {
            $supplierStatus = trim((string) ($variables['supplier_booking_status'] ?? ''));
            if ($supplierStatus !== '') {
                $variables['supplier_status'] = $supplierStatus;
            }
        }

        return EmailPlaceholderFallbacks::applyVariableAliases($variables);
    }

    protected static function resolveSupportEmail(?string $profileEmail): string
    {
        $email = trim((string) ($profileEmail ?? ''));
        if ($email !== '') {
            return $email;
        }

        return trim((string) config('mail.from.address', ''));
    }

    protected static function resolveSupportPhone(?string $profilePhone): string
    {
        return trim((string) ($profilePhone ?? ''));
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, scalar|null>
     */
    protected static function normalizeScalars(array $variables): array
    {
        $normalized = [];
        foreach ($variables as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $normalized[(string) $key] = $value;
            }
        }

        return $normalized;
    }

    protected static function resolveBrandName(Agency $agency): string
    {
        $fromBranding = trim(BrandDisplayResolver::displayName($agency->agencySetting));
        if ($fromBranding !== '' && ! EmailPlaceholderFallbacks::isForbiddenBrandName($fromBranding)) {
            return $fromBranding;
        }

        $fromConfig = trim((string) config('app.name', ''));
        if ($fromConfig !== '' && ! EmailPlaceholderFallbacks::isForbiddenBrandName($fromConfig)) {
            return $fromConfig;
        }

        return self::defaultBrandName();
    }

    protected static function defaultBrandName(): string
    {
        if (function_exists('uses_jetpk_company_branding') && uses_jetpk_company_branding()) {
            $fromJetpk = trim((string) (function_exists('jetpk_company_branding') ? jetpk_company_branding()->companyName() : ''));

            return $fromJetpk !== '' ? $fromJetpk : 'JetPakistan';
        }

        return self::NEUTRAL_BRAND_NAME;
    }

    protected static function manageBookingUrl(Booking $booking): string
    {
        if ($booking->customer_id !== null && Route::has('customer.bookings.show')) {
            return route('customer.bookings.show', $booking, absolute: true);
        }

        if ($booking->customer_id !== null && Route::has('customer.bookings.index')) {
            return route('customer.bookings.index', absolute: true);
        }

        if (Route::has('booking.lookup')) {
            return route('booking.lookup', absolute: true);
        }

        return '';
    }

    protected static function adminBookingUrl(Booking $booking): string
    {
        if (! Route::has('admin.bookings.show')) {
            return '';
        }

        return route('admin.bookings.show', $booking, absolute: true);
    }
}
