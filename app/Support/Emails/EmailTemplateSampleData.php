<?php

namespace App\Support\Emails;

use App\Support\Branding\CompanyEmailProfile;

/**
 * Realistic placeholder values for admin email template previews (I4; preview-only).
 */
class EmailTemplateSampleData
{
    /**
     * Full catalog of sample variables for previews and documentation.
     *
     * @return array<string, string>
     */
    public static function all(CompanyEmailProfile $profile): array
    {
        $website = $profile->website_url ?? 'https://www.example-travel.test';

        return [
            'customer_name' => 'Sarah Ahmed',
            'passenger_name' => 'Sarah Ahmed',
            'user_name' => 'Sarah Ahmed',
            'booking_reference' => 'GXJDHD8K',
            'pnr' => 'ABC123',
            'origin' => 'Lahore (LHE)',
            'destination' => 'Dubai (DXB)',
            'route' => 'LHE — DXB',
            'departure_date' => '15 Jul 2026',
            'return_date' => '22 Jul 2026',
            'travel_date' => '15 Jul 2026',
            'airline' => 'Emirates',
            'amount' => '128,450.00',
            'currency' => 'PKR',
            'payment_status' => 'Verified',
            'booking_status' => 'Confirmed',
            'agent_name' => 'Hassan Raza',
            'agency_name' => $profile->name,
            'company_name' => $profile->name,
            'support_email' => $profile->support_email ?? 'support@example-travel.test',
            'support_phone' => $profile->support_phone ?? '+92 300 1234567',
            'website_url' => $website,
            'ticket_number' => '176-1234567890',
            'refund_amount' => '45,000.00',
            'cancellation_reason' => 'Customer requested schedule change',
            'support_ticket_reference' => 'S7KQ92MD',
            'login_url' => rtrim($website, '/').'/login',
            'verification_url' => rtrim($website, '/').'/email/verify/sample-token',
            'reset_url' => rtrim($website, '/').'/password/reset/sample-token',
            'resume_url' => rtrim($website, '/').'/flights/search?resume=sample',
            'search_route' => 'Karachi (KHI) — Istanbul (IST)',
            'depart_date' => '20 Aug 2026',
            'period_label' => 'March 2026',
            'user_email' => 'sarah.ahmed@example.test',
            'phone' => '+92 321 9876543',
            'account_type' => 'Platform Administrator',
            'timestamp' => '05 Jun 2026, 14:30 PKT',
            'ip' => '203.0.113.42',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'portal_label' => 'Admin Portal',
            'brand_name' => $profile->name,
            'brand_logo_url' => (string) ($profile->logo_url ?? ''),
            'brand_support_email' => (string) ($profile->support_email ?? ''),
            'brand_phone' => (string) ($profile->support_phone ?? ''),
            'brand_website' => (string) ($profile->website_url ?? ''),
            'manage_booking_url' => rtrim($website, '/').'/bookings/sample',
            'support_url' => rtrim($website, '/').'/support',
            'created_at' => '05 Jun 2026, 14:30',
            'fare_total' => '128,450.00',
            'trip_type' => 'Return',
            'customer_phone' => '+92 321 9876543',
            'passenger_summary' => '2 adults, 1 child',
            'review_reason' => 'Staff review required',
            'supplier_status' => 'Pending / Staff review',
            'applicant_name' => 'Hassan Raza',
            'city' => 'Lahore',
            'login_email' => 'agent@example-travel.test',
            'information_required' => 'Please provide company registration documents.',
            'rejection_reason' => 'Incomplete documentation',
            'ticket_reference' => 'S7KQ92MD',
            'ticket_subject' => 'Booking amendment request',
            'requester_name' => 'Sarah Ahmed',
            'requester_email' => 'sarah.ahmed@example.test',
            'ticket_status' => 'Open',
        ];
    }

    /**
     * Variables relevant to a registry definition (plus company profile fields).
     *
     * @return array<string, string>
     */
    public static function forDefinition(EmailTemplateDefinition $definition, CompanyEmailProfile $profile): array
    {
        $all = self::all($profile);
        $keys = array_unique(array_merge(
            $definition->variables,
            [
                'agency_name', 'company_name', 'brand_name', 'brand_logo_url',
                'brand_support_email', 'brand_phone', 'brand_website',
                'support_email', 'support_phone', 'website_url', 'support_url',
                'booking_reference', 'booking_status', 'customer_name',
            ],
        ));

        $subset = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $subset[$key] = $all[$key];
            }
        }

        return $subset;
    }
}
