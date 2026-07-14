<?php

namespace App\Support\Emails;

/**
 * Central safe fallback values for email template placeholders when data is missing.
 */
class EmailPlaceholderFallbacks
{
    private const NEUTRAL_BRAND_NAME = 'Travel Platform';

    /**
     * @return list<string>
     */
    private static function forbiddenBrandFragments(): array
    {
        $configured = config('jetpk_email.forbidden_brand_fragments', []);

        return is_array($configured) ? array_values(array_filter($configured, 'is_string')) : [];
    }

    /** @var array<string, string> */
    private const OPS_FALLBACKS = [
        'amount' => 'Not available',
        'currency' => 'PKR',
        'pnr' => 'Not assigned yet',
        'supplier_status' => 'Pending / Staff review',
        'review_reason' => 'Staff review required',
        'applicant_name' => 'Applicant',
        'city' => 'Not provided',
        'login_email' => 'Not provided',
        'information_required' => 'Additional information required',
        'rejection_reason' => 'Not specified',
        'ticket_reference' => 'To be assigned',
        'ticket_subject' => 'Support request',
        'requester_name' => 'Requester',
        'requester_email' => 'Not provided',
        'ticket_status' => 'Open',
        'booking_reference' => 'Booking reference pending',
        'passenger_name' => 'Passenger',
        'customer_name' => 'Customer',
        'customer_email' => 'Not provided',
        'route' => 'Route pending',
        'travel_date' => 'To be confirmed',
        'booking_status' => 'Pending',
        'trip_type' => 'One way',
        'payment_status' => 'Pending',
        'fare_total' => 'Not available',
        'user_name' => 'User',
        'user_email' => 'Not provided',
        'account_type' => 'Account',
        'timestamp' => 'Not available',
        'ip' => 'Not available',
        'user_agent' => 'Not available',
        'portal_label' => 'Portal',
        'agent_name' => 'Agent',
        'period_label' => 'Current period',
        'phone' => 'Not provided',
        'search_route' => 'Route pending',
        'depart_date' => 'To be confirmed',
        'return_date' => 'To be confirmed',
        'resume_url' => 'Not available',
    ];

    /** @var array<string, string> */
    private const CUSTOMER_OVERRIDES = [
        'supplier_status' => 'In progress',
    ];

    /** @var array<string, string> */
    private const ALIASES = [
        'staff_review_reason' => 'review_reason',
        'supplier_booking_status' => 'supplier_status',
        'manual_review_reason' => 'review_reason',
    ];

    /**
     * @param  array{audience?: string, brand_name?: string, event_key?: string, template_key?: string}|null  $context
     */
    public static function fallbackFor(string $key, ?array $context = null): ?string
    {
        $canonical = self::canonicalKey($key);
        $audience = (string) ($context['audience'] ?? '');

        if ($audience === 'customer' && isset(self::CUSTOMER_OVERRIDES[$canonical])) {
            return self::CUSTOMER_OVERRIDES[$canonical];
        }

        if ($canonical === 'brand_name') {
            return self::resolveBrandName($context);
        }

        if ($canonical === 'agency_name' || $canonical === 'company_name') {
            return self::resolveAgencyOrCompanyName($context);
        }

        if ($canonical === 'support_email') {
            $fromConfig = trim((string) config('mail.from.address', ''));

            return $fromConfig !== '' ? $fromConfig : 'Not provided';
        }

        if ($canonical === 'support_phone') {
            return 'Not provided';
        }

        return self::OPS_FALLBACKS[$canonical] ?? null;
    }

    public static function isForbiddenBrandName(string $name): bool
    {
        $trimmed = trim($name);
        if ($trimmed === '' || str_contains($trimmed, '{{')) {
            return true;
        }

        foreach (self::forbiddenBrandFragments() as $forbidden) {
            if (stripos($trimmed, $forbidden) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{audience?: string, brand_name?: string, agency_name?: string, company_name?: string}|null  $context
     */
    public static function resolveBrandName(?array $context = null): string
    {
        $context = $context ?? [];
        $brand = trim((string) ($context['brand_name'] ?? ''));
        if ($brand !== '' && ! self::isForbiddenBrandName($brand)) {
            return $brand;
        }

        return self::defaultBrandName();
    }

    /**
     * @param  array{audience?: string, brand_name?: string, agency_name?: string, company_name?: string}|null  $context
     */
    public static function resolveAgencyOrCompanyName(?array $context = null): string
    {
        $context = $context ?? [];

        foreach (['agency_name', 'company_name', 'brand_name'] as $key) {
            $value = trim((string) ($context[$key] ?? ''));
            if ($value !== '' && ! self::isForbiddenBrandName($value)) {
                return $value;
            }
        }

        return self::defaultBrandName();
    }

    protected static function defaultBrandName(): string
    {
        if (function_exists('uses_jetpk_company_branding') && uses_jetpk_company_branding()) {
            $fromJetpk = trim((string) (function_exists('jetpk_company_branding') ? jetpk_company_branding()->companyName() : ''));
            $candidate = $fromJetpk !== '' ? $fromJetpk : 'JetPakistan';

            return self::isForbiddenBrandName($candidate) ? 'JetPakistan' : $candidate;
        }

        return self::NEUTRAL_BRAND_NAME;
    }

    public static function canonicalKey(string $key): string
    {
        return self::ALIASES[$key] ?? $key;
    }

    /**
     * @param  array<string, scalar|null>  $variables
     * @return array<string, string>
     */
    public static function applyVariableAliases(array $variables): array
    {
        foreach (self::ALIASES as $alias => $canonical) {
            $aliasValue = trim((string) ($variables[$alias] ?? ''));
            $canonicalValue = trim((string) ($variables[$canonical] ?? ''));

            if ($aliasValue !== '' && $canonicalValue === '') {
                $variables[$canonical] = $aliasValue;
            }
        }

        return $variables;
    }

    /**
     * @return list<string>
     */
    public static function knownFallbackKeys(): array
    {
        return array_values(array_unique(array_merge(
            array_keys(self::OPS_FALLBACKS),
            array_keys(self::CUSTOMER_OVERRIDES),
            ['brand_name', 'agency_name', 'company_name', 'support_email', 'support_phone'],
        )));
    }
}
