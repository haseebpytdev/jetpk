<?php

namespace App\Support\Emails;

/**
 * JetpkEmailSampleData
 *
 * Safe, fake sample payloads used ONLY by preview/audit commands.
 * No real user, booking, payment, passenger, or OTP data is used here.
 * Never call suppliers, never write DB, never send email.
 */
trait JetpkEmailSampleData
{
    /**
     * Build a null-safe sample payload for a given email type.
     *
     * @return array<string, mixed>
     */
    protected function sampleData(string $type): array
    {
        $brand = JetpkEmailBrandingResolver::resolve('jetpk');

        // Ensure previews show support details even before branding is wired.
        $brand['support_email'] = $brand['support_email'] ?? 'ota@jetpakistan.pk';
        $brand['support_phone'] = $brand['support_phone'] ?? '+92 21 111 000 000';
        $brand['manage_url']    = $brand['manage_url'] ?? ($brand['home_url'] ?? config('app.url', 'https://jetpakistan.pk'));

        $brandName = trim((string) ($brand['brand_name'] ?? 'JetPakistan')) ?: 'JetPakistan';
        $companyName = trim((string) ($brand['legal_name'] ?? $brandName)) ?: $brandName;

        $home = $brand['home_url'] ?? config('app.url', 'https://jetpakistan.pk');

        $base = [
            'emailBrand'    => $brand,
            'recipientName' => 'Ayesha Khan',
            'brand_name'    => $brandName,
            'agency_name'   => $brandName,
            'company_name'  => $companyName,
            'support_email' => $brand['support_email'],
            'support_phone' => $brand['support_phone'],
            'support'       => [
                'email' => $brand['support_email'],
                'phone' => $brand['support_phone'],
                'hours' => 'Sun–Sat, 9:00–21:00 PKT',
            ],
        ];

        $booking = [
            'reference'       => 'JPK-2026-004821',
            'pnr'             => null,
            'status'          => 'Pending',
            'payment_status'  => 'Unpaid',
            'route'           => 'Karachi (KHI) → Dubai (DXB)',
            'trip_type'       => 'One way',
            'passenger_count' => 2,
            'amount'          => '96,500',
            'currency'        => 'PKR',
            'payment_deadline' => '10 Jul 2026, 6:00 PM',
        ];

        $bookingConfirmed = array_merge($booking, [
            'pnr'            => 'X7K9QP',
            'status'         => 'Confirmed',
            'payment_status' => 'Paid',
        ]);

        $itinerary = [
            [
                'label'   => 'Outbound',
                'from'    => 'KHI', 'from_name' => 'Jinnah Intl',
                'to'      => 'DXB', 'to_name' => 'Dubai Intl',
                'depart'  => '10 Jul 2026, 08:20',
                'arrive'  => '10 Jul 2026, 10:05',
                'airline' => 'Sample Air', 'flight_no' => 'SA-311',
                'stops'   => 'Non-stop', 'baggage' => '30kg',
            ],
        ];

        $passengers = [
            ['name' => 'Ayesha Khan', 'type' => 'Adult'],
            ['name' => 'Bilal Khan', 'type' => 'Adult'],
        ];

        $payment = [
            'amount'         => '96,500',
            'currency'       => 'PKR',
            'method'         => 'Card',
            'status'         => 'Paid',
            'reference'      => 'TXN-4F9A21C7',
            'transaction_id' => 'TXN-4F9A21C7',
            'invoice_number' => 'INV-2026-004821',
            'paid_at'        => '09 Jul 2026, 3:42 PM',
            'invoice_url'    => $home . '/account/invoices/INV-2026-004821',
        ];

        switch ($type) {
            case 'otp':
                return array_merge($base, [
                    'subjectText'   => 'Your JetPakistan verification code',
                    'preheaderText' => 'Use this code to verify your sign-in.',
                    'headline'      => 'Verify your sign-in',
                    'introText'     => 'Use the one-time code below to continue.',
                    'security'      => ['otp' => '482913', 'expiry_minutes' => 10, 'context' => 'Web sign-in'],
                ]);

            case 'sign_in_success':
                return array_merge($base, [
                    'subjectText'   => 'New sign-in to your JetPakistan account',
                    'preheaderText' => 'We noticed a new sign-in to your account.',
                    'headline'      => 'New sign-in detected',
                    'introText'     => 'Here are the details of a recent sign-in.',
                    'ctaText'       => 'View dashboard',
                    'ctaUrl'        => $home . '/account',
                    'security'      => [
                        'login_time' => '09 Jul 2026, 3:10 PM PKT',
                        'device'     => 'iPhone', 'browser' => 'Safari',
                        'ip'         => '203.0.113.42', 'location' => 'Lahore, Pakistan',
                    ],
                ]);

            case 'password_reset':
                return array_merge($base, [
                    'subjectText'   => 'Reset your JetPakistan password',
                    'preheaderText' => 'Reset your password with this secure link.',
                    'headline'      => 'Reset your password',
                    'introText'     => 'Tap the button below to set a new password.',
                    'ctaText'       => 'Reset password',
                    'ctaUrl'        => $home . '/password/reset/sample-token',
                    'security'      => ['expiry_minutes' => 60],
                ]);

            case 'account_created':
                return array_merge($base, [
                    'subjectText'   => 'Welcome to JetPakistan',
                    'preheaderText' => 'Your account is ready.',
                    'headline'      => 'Welcome to JetPakistan',
                    'ctaText'       => 'Go to dashboard',
                    'ctaUrl'        => $home . '/account',
                    'meta'          => ['account_type' => 'Customer', 'email' => 'ayesha@example.com'],
                ]);

            case 'email_verification':
                return array_merge($base, [
                    'subjectText'   => 'Verify your JetPakistan email',
                    'headline'      => 'Verify your email',
                    'ctaText'       => 'Verify email',
                    'ctaUrl'        => $home . '/email/verify/sample',
                    'meta'          => ['verify_url' => $home . '/email/verify/sample'],
                ]);

            case 'password_changed':
                return array_merge($base, [
                    'subjectText' => 'Your JetPakistan password was changed',
                    'headline'    => 'Password changed',
                ]);

            case 'security_notice':
                return array_merge($base, [
                    'subjectText' => 'Security notice — JetPakistan',
                    'headline'    => 'Unusual sign-in activity',
                    'meta'        => ['message' => 'We noticed a sign-in from a new device.'],
                    'security'    => [
                        'login_time' => '09 Jul 2026, 3:10 PM PKT',
                        'device' => 'Android', 'browser' => 'Chrome',
                        'location' => 'Karachi, Pakistan',
                    ],
                ]);

            case 'pnr_created':
                return array_merge($base, [
                    'subjectText' => 'PNR created — JetPakistan',
                    'headline'    => 'PNR created',
                    'booking'     => array_merge($booking, ['pnr' => 'X7K9QP', 'status' => 'Pending review']),
                    'meta'        => ['pnr_note' => 'Your PNR is pending manual ticketing review.'],
                ]);

            case 'group_reservation_created':
                return array_merge($base, [
                    'subjectText' => 'Group reservation created — JetPakistan',
                    'headline'    => 'Group reservation held',
                    'reservation' => [
                        'reference' => 'GRP-2026-1192',
                        'route' => 'LHE → JED',
                        'seats' => '12',
                        'deadline' => '12 Jul 2026, 6:00 PM',
                    ],
                ]);

            case 'group_reservation_expiring':
                return array_merge($base, [
                    'subjectText' => 'Group reservation expiring — JetPakistan',
                    'headline'    => 'Reservation expiring soon',
                    'reservation' => ['reference' => 'GRP-2026-1192', 'expires_at' => '12 Jul 2026, 6:00 PM'],
                ]);

            case 'agent_registration_received':
                return array_merge($base, [
                    'subjectText' => 'Agency application received — JetPakistan',
                    'headline'    => 'Application received',
                    'application' => ['agency_name' => 'Sample Travels', 'reference' => 'AG-2026-004'],
                ]);

            case 'agent_registration_approved':
                return array_merge($base, [
                    'subjectText' => 'Agency approved — JetPakistan',
                    'headline'    => 'Welcome, partner',
                    'ctaText'     => 'Open agent portal',
                    'ctaUrl'      => $home . '/agent',
                ]);

            case 'admin_operational_notification':
                return array_merge($base, [
                    'subjectText' => 'Operations alert — JetPakistan',
                    'headline'    => 'Booking requires review',
                    'meta'        => [
                        'alert_type' => 'warning',
                        'alert_title' => 'Manual review',
                        'message' => 'Booking JPK-2026-004821 requires operator approval before ticketing.',
                        'booking_reference' => 'JPK-2026-004821',
                    ],
                ]);

            case 'booking_created':
                return array_merge($base, [
                    'subjectText'   => 'Your JetPakistan booking request',
                    'preheaderText' => 'We\'ve received your booking request.',
                    'headline'      => 'Booking request received',
                    'ctaText'       => 'View booking',
                    'ctaUrl'        => $home . '/account/bookings/JPK-2026-004821',
                    'booking'       => $booking,
                    'itinerary'     => $itinerary,
                    'passengers'    => $passengers,
                ]);

            case 'booking_pending_manual_payment':
                return array_merge($base, [
                    'subjectText'   => 'Action needed: complete your JetPakistan payment',
                    'preheaderText' => 'Complete payment to confirm your booking.',
                    'headline'      => 'Complete your payment',
                    'ctaText'       => 'View booking',
                    'ctaUrl'        => $home . '/account/bookings/JPK-2026-004821',
                    'booking'       => $booking,
                    'payment'       => ['instructions' => "Bank: Sample Bank\nTitle: JetPakistan\nAccount/IBAN: PK00SAMP0000000000000000\nReference: JPK-2026-004821"],
                ]);

            case 'booking_confirmed':
                return array_merge($base, [
                    'subjectText'   => 'Your JetPakistan booking is confirmed',
                    'preheaderText' => 'Your booking is confirmed. Here are the details.',
                    'headline'      => 'Booking confirmed',
                    'ctaText'       => 'Manage booking',
                    'ctaUrl'        => $home . '/account/bookings/JPK-2026-004821',
                    'booking'       => $bookingConfirmed,
                    'itinerary'     => $itinerary,
                    'passengers'    => $passengers,
                ]);

            case 'booking_failed':
                return array_merge($base, [
                    'subjectText'   => 'We couldn\'t complete your JetPakistan booking',
                    'preheaderText' => 'Your booking could not be completed.',
                    'headline'      => 'Booking not completed',
                    'ctaText'       => 'Try again',
                    'ctaUrl'        => $home . '/flights',
                    'booking'       => ['reference' => 'JPK-2026-004821'],
                ]);

            case 'booking_cancelled':
                return array_merge($base, [
                    'subjectText'   => 'Your JetPakistan booking has been cancelled',
                    'preheaderText' => 'This booking has been cancelled.',
                    'headline'      => 'Booking cancelled',
                    'booking'       => array_merge($bookingConfirmed, ['status' => 'Cancelled']),
                    'itinerary'     => $itinerary,
                    'meta'          => ['refund_info' => 'A refund of PKR 90,000 will be processed to your original method within 7–10 business days.'],
                ]);

            case 'booking_updated':
                return array_merge($base, [
                    'subjectText'   => 'Your JetPakistan booking has been updated',
                    'preheaderText' => 'Your booking details have changed.',
                    'headline'      => 'Booking updated',
                    'ctaText'       => 'View booking',
                    'ctaUrl'        => $home . '/account/bookings/JPK-2026-004821',
                    'booking'       => $bookingConfirmed,
                    'itinerary'     => $itinerary,
                    'passengers'    => $passengers,
                    'meta'          => ['change_summary' => 'Your outbound departure time changed from 08:20 to 09:05.'],
                ]);

            case 'booking_expiring':
                return array_merge($base, [
                    'subjectText'   => 'Your JetPakistan booking is about to expire',
                    'preheaderText' => 'Complete payment before your booking expires.',
                    'headline'      => 'Your booking is about to expire',
                    'ctaText'       => 'Complete payment',
                    'ctaUrl'        => $home . '/account/bookings/JPK-2026-004821',
                    'booking'       => $booking,
                ]);

            case 'manual_payment_received':
                return array_merge($base, [
                    'subjectText'   => 'We\'ve received your JetPakistan payment details',
                    'preheaderText' => 'Your payment is under review.',
                    'headline'      => 'Payment under review',
                    'booking'       => $booking,
                    'payment'       => array_merge($payment, ['method' => 'Bank transfer', 'status' => 'Under review']),
                ]);

            case 'payment_success':
                return array_merge($base, [
                    'subjectText'   => 'Payment received — JetPakistan',
                    'preheaderText' => 'Your payment was successful.',
                    'headline'      => 'Payment successful',
                    'ctaText'       => 'Download invoice',
                    'ctaUrl'        => $home . '/account/invoices/INV-2026-004821',
                    'booking'       => $bookingConfirmed,
                    'payment'       => $payment,
                ]);

            case 'payment_failed':
                return array_merge($base, [
                    'subjectText'   => 'Your JetPakistan payment didn\'t go through',
                    'preheaderText' => 'We couldn\'t process your payment.',
                    'headline'      => 'Payment failed',
                    'ctaText'       => 'Retry payment',
                    'ctaUrl'        => $home . '/account/bookings/JPK-2026-004821/pay',
                    'booking'       => $booking,
                    'payment'       => array_merge($payment, ['status' => 'Failed']),
                ]);

            case 'invoice':
                return array_merge($base, [
                    'subjectText'   => 'Your JetPakistan invoice INV-2026-004821',
                    'preheaderText' => 'Here is your invoice.',
                    'headline'      => 'Invoice',
                    'ctaText'       => 'Download invoice',
                    'ctaUrl'        => $home . '/account/invoices/INV-2026-004821',
                    'booking'       => $bookingConfirmed,
                    'payment'       => $payment,
                    'meta'          => [
                        'customer_name' => 'Ayesha Khan',
                        'items'    => [
                            ['label' => 'Base fare (x2)', 'amount' => '82,000'],
                            ['label' => 'Baggage', 'amount' => '6,000'],
                        ],
                        'subtotal' => '88,000',
                        'taxes'    => '6,500',
                        'fees'     => '2,000',
                        'total'    => '96,500',
                    ],
                ]);

            case 'refund_requested':
                return array_merge($base, [
                    'subjectText'   => 'Your JetPakistan refund request',
                    'preheaderText' => 'We\'ve received your refund request.',
                    'headline'      => 'Refund requested',
                    'booking'       => $bookingConfirmed,
                    'payment'       => ['amount' => '90,000', 'currency' => 'PKR', 'status' => 'Requested'],
                ]);

            case 'refund_updated':
                return array_merge($base, [
                    'subjectText'   => 'Update on your JetPakistan refund',
                    'preheaderText' => 'Your refund status has changed.',
                    'headline'      => 'Refund update',
                    'booking'       => $bookingConfirmed,
                    'payment'       => ['amount' => '90,000', 'currency' => 'PKR', 'status' => 'Refunded'],
                    'meta'          => ['refund_note' => 'Your refund of PKR 90,000 has been processed to your original payment method.'],
                ]);

            case 'support_ticket_created':
                return array_merge($base, [
                    'subjectText'   => 'We\'ve received your request — JetPakistan',
                    'preheaderText' => 'Your support request has been logged.',
                    'headline'      => 'Support request received',
                    'support'       => array_merge($base['support'], [
                        'ticket_reference' => 'TKT-88213',
                        'subject'          => 'Change passenger name',
                        'status'           => 'Open',
                    ]),
                ]);

            case 'support_reply':
                return array_merge($base, [
                    'subjectText'   => 'Reply to your JetPakistan request TKT-88213',
                    'preheaderText' => 'Our team has replied to your request.',
                    'headline'      => 'Reply from support',
                    'support'       => array_merge($base['support'], [
                        'ticket_reference' => 'TKT-88213',
                        'subject'          => 'Change passenger name',
                        'status'           => 'In progress',
                        'response'         => "Thanks for reaching out. We've updated the passenger name as requested. Please review your booking and let us know if anything else is needed.",
                        'next_action'      => 'Review your updated booking in your account.',
                    ]),
                ]);

            case 'notification':
            default:
                return array_merge($base, [
                    'subjectText'   => 'A quick update from JetPakistan',
                    'preheaderText' => 'We have an update for you.',
                    'headline'      => 'Notification',
                    'introText'     => 'Here is a quick update.',
                    'meta'          => ['message' => 'This is a general notification message from JetPakistan.'],
                ]);
        }
    }
}
