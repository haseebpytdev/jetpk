<?php

namespace App\Support\Emails;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\User;
use App\Support\Branding\CompanyEmailProfile;
use App\Support\Branding\CompanyEmailProfileResolver;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Live send renderer for customer-facing booking/payment/ticket Mailables (I7).
 */
class CustomerFacingEmailRenderer
{
    public function bookingRequestReceived(Booking $booking): CustomerFacingEmailRendered
    {
        $booking->loadMissing(['agency.agencySetting', 'contact', 'passengers', 'customer', 'fareBreakdown']);
        $profile = CompanyEmailProfileResolver::resolve($booking->agency);
        $reference = $booking->reference_code;
        $name = $this->contactName($booking);
        $rows = $this->bookingDetailRows($booking, [
            ['label' => 'Status', 'value' => $this->formatBookingStatus($booking)],
        ]);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $selectedFareIntent = is_array($meta['selected_fare_family_option'] ?? null)
            ? $meta['selected_fare_family_option']
            : null;
        $selectedFareFamily = FlightOfferDisplayPresenter::buildSelectedFareFamilyEmailSection($selectedFareIntent);
        $contentParts = [];

        if ($selectedFareFamily !== null) {
            if (! empty($selectedFareFamily['fare_family_label'])) {
                $rows[] = ['label' => 'Selected fare family', 'value' => (string) $selectedFareFamily['fare_family_label']];
            }
            if (! empty($selectedFareFamily['estimated_fare_display'])) {
                $rows[] = [
                    'label' => (string) ($selectedFareFamily['estimated_fare_label'] ?? 'Estimated selected fare'),
                    'value' => (string) $selectedFareFamily['estimated_fare_display'],
                ];
            }
            foreach ([
                'Baggage' => 'baggage',
                'Cabin' => 'cabin',
                'Booking class' => 'booking_class',
                'Fare basis' => 'fare_basis',
            ] as $label => $key) {
                if (! empty($selectedFareFamily[$key])) {
                    $rows[] = ['label' => $label, 'value' => (string) $selectedFareFamily[$key]];
                }
            }
            if (! empty($selectedFareFamily['validation_note'])) {
                $contentParts[] = '<p style="margin:12px 0 0;color:#64748b;font-size:12px;line-height:1.45;">'.e((string) $selectedFareFamily['validation_note']).'</p>';
            }
        } else {
            $total = $booking->fareBreakdown?->total;
            if ($total !== null && (float) $total > 0) {
                $rows[] = ['label' => 'Total', 'value' => ($booking->currency ?? 'PKR').' '.number_format((float) $total, 2)];
            }
        }

        $passengerBlock = $this->passengerListHtml($booking);
        if ($passengerBlock !== '') {
            $contentParts[] = $passengerBlock;
        }
        $contentHtml = $contentParts !== [] ? implode('', $contentParts) : null;
        $cta = $this->customerBookingCta($booking);
        $footerDisclaimer = 'Please keep this email for your records. This is not a ticket or payment receipt.';
        if ($selectedFareFamily !== null && ! empty($selectedFareFamily['payable_disclaimer'])) {
            $footerDisclaimer .= ' '.(string) $selectedFareFamily['payable_disclaimer'];
        }

        return $this->render(
            profile: $profile,
            headline: 'Booking request received',
            intro: sprintf('Hello %s, thank you — we have received your booking request. Our team will review the details and contact you with the next steps.', $name),
            contentHtml: $contentHtml,
            details: $rows,
            ctaUrl: $cta['url'] ?? null,
            ctaLabel: $cta['label'] ?? null,
            statusBannerLabel: 'Booking request received',
            statusBannerTone: 'info',
            nextSteps: [
                'Our team will review your booking details.',
                'We will email you when your booking is confirmed, ticketed, or needs more information.',
                'Keep your booking reference handy when contacting support.',
            ],
            footerDisclaimer: $footerDisclaimer,
            plainBody: $this->plainLines([
                'Hello '.$name.',',
                '',
                'Your booking request has been received.',
                'Reference: '.$reference,
                '',
                'We will email you again when your booking is updated, ticketed, or when documents are ready.',
            ]),
        );
    }

    public function bookingStatusChanged(Booking $booking, string $statusLabel): CustomerFacingEmailRendered
    {
        $booking->loadMissing(['agency.agencySetting', 'contact', 'customer']);
        $profile = CompanyEmailProfileResolver::resolve($booking->agency);
        $name = $this->contactName($booking);
        $rows = $this->bookingDetailRows($booking, [
            ['label' => 'New status', 'value' => $this->formatStatusLabel($statusLabel)],
        ]);
        $cta = $this->customerBookingCta($booking);

        return $this->render(
            profile: $profile,
            headline: 'Booking status updated',
            intro: sprintf('Hello %s, your booking status has been updated.', $name),
            contentHtml: null,
            details: $rows,
            ctaUrl: $cta['url'] ?? null,
            ctaLabel: $cta['label'] ?? null,
            statusBannerLabel: 'Status update: '.$this->formatStatusLabel($statusLabel),
            statusBannerTone: 'neutral',
            nextSteps: [
                'Review the updated status in your booking summary below.',
                'Contact support if you did not expect this change.',
            ],
            footerDisclaimer: 'Please keep this email for your records. If you did not expect this change, contact support with your booking reference.',
            plainBody: $this->plainLines([
                'Hello '.$name.',',
                '',
                'Your booking status has been updated.',
                'Reference: '.$booking->reference_code,
                'New status: '.$this->formatStatusLabel($statusLabel),
            ]),
        );
    }

    public function paymentVerified(BookingPayment $payment): CustomerFacingEmailRendered
    {
        $payment->loadMissing(['booking.agency.agencySetting', 'booking.contact', 'booking.customer']);
        $booking = $payment->booking;
        $profile = CompanyEmailProfileResolver::resolve($booking->agency);
        $name = $this->contactName($booking);
        $rows = [
            ['label' => 'Booking reference', 'value' => $booking->reference_code],
            ['label' => 'Amount', 'value' => number_format((float) $payment->amount, 2).' '.($payment->currency ?? 'PKR')],
            ['label' => 'Method', 'value' => $this->formatStatusLabel($payment->method->value)],
            ['label' => 'Payment status', 'value' => 'Verified'],
        ];
        $cta = $this->customerBookingCta($booking);

        return $this->render(
            profile: $profile,
            headline: 'Payment verified',
            intro: sprintf('Hello %s, we have verified your payment. Thank you.', $name),
            contentHtml: null,
            details: $rows,
            ctaUrl: $cta['url'] ?? null,
            ctaLabel: $cta['label'] ?? null,
            statusBannerLabel: 'Payment verified',
            statusBannerTone: 'success',
            nextSteps: [
                'Your payment has been recorded against your booking.',
                'Your ticket or itinerary may follow in a separate email when ready.',
            ],
            footerDisclaimer: 'Please keep this email for your records. This confirms payment verification only.',
            plainBody: $this->plainLines([
                'Hello '.$name.',',
                '',
                'We have verified your payment.',
                'Booking reference: '.$booking->reference_code,
                'Amount: '.number_format((float) $payment->amount, 2).' '.($payment->currency ?? 'PKR'),
                'Status: Verified',
            ]),
        );
    }

    public function paymentRejected(BookingPayment $payment): CustomerFacingEmailRendered
    {
        $payment->loadMissing(['booking.agency.agencySetting', 'booking.contact', 'booking.customer']);
        $booking = $payment->booking;
        $profile = CompanyEmailProfileResolver::resolve($booking->agency);
        $name = $this->contactName($booking);
        $rows = [
            ['label' => 'Booking reference', 'value' => $booking->reference_code],
            ['label' => 'Amount', 'value' => number_format((float) $payment->amount, 2).' '.($payment->currency ?? 'PKR')],
            ['label' => 'Payment status', 'value' => 'Could not be verified'],
        ];
        $cta = $this->customerBookingCta($booking);

        return $this->render(
            profile: $profile,
            headline: 'Payment could not be verified',
            intro: sprintf('Hello %s, we reviewed your payment submission but could not verify it at this time. Please submit updated proof or contact our support team.', $name),
            contentHtml: null,
            details: $rows,
            ctaUrl: $cta['url'] ?? null,
            ctaLabel: $cta['label'] ?? 'View booking',
            statusBannerLabel: 'Payment requires attention',
            statusBannerTone: 'warning',
            nextSteps: [
                'Submit updated payment proof through your booking page.',
                'Contact support if you need help with your payment.',
            ],
            footerDisclaimer: 'Please keep this email for your records. For your security we do not include internal review notes in this email.',
            plainBody: $this->plainLines([
                'Hello '.$name.',',
                '',
                'Your payment submission could not be verified.',
                'Booking reference: '.$booking->reference_code,
                'Please submit updated proof or contact support.',
            ]),
        );
    }

    public function ticketIssued(Booking $booking): CustomerFacingEmailRendered
    {
        $booking->loadMissing(['agency.agencySetting', 'contact', 'customer', 'tickets']);
        $profile = CompanyEmailProfileResolver::resolve($booking->agency);
        $name = $this->contactName($booking);
        $rows = $this->bookingDetailRows($booking, [
            ['label' => 'PNR', 'value' => ModernEmailLayout::customerPnrDisplay($booking->pnr)],
            ['label' => 'Tickets', 'value' => (string) $booking->tickets->count()],
        ]);
        $ticketHtml = $this->ticketNumbersHtml($booking);
        $cta = $this->customerBookingCta($booking);

        return $this->render(
            profile: $profile,
            headline: 'Ticket issued',
            intro: sprintf('Hello %s, your booking has been ticketed.', $name),
            contentHtml: $ticketHtml !== '' ? $ticketHtml : null,
            details: $rows,
            ctaUrl: $cta['url'] ?? null,
            ctaLabel: $cta['label'] ?? null,
            statusBannerLabel: 'Ticket issued',
            statusBannerTone: 'success',
            nextSteps: [
                'Keep your ticket numbers and booking reference for travel.',
                'A full itinerary PDF may be sent separately when it is ready.',
            ],
            footerDisclaimer: 'Please keep this email for your records. Keep your ticket numbers confidential.',
            plainBody: $this->plainLines(array_merge(
                ['Hello '.$name.',', '', 'Your booking has been ticketed.', 'Reference: '.$booking->reference_code],
                $this->ticketPlainLines($booking),
                ['', 'A full itinerary PDF may be sent separately when it is ready.'],
            )),
        );
    }

    public function itineraryReady(Booking $booking, ?string $staffNote = null, bool $hasPdfAttachment = false): CustomerFacingEmailRendered
    {
        $booking->loadMissing(['agency.agencySetting', 'contact', 'customer', 'tickets']);
        $profile = CompanyEmailProfileResolver::resolve($booking->agency);
        $name = $this->contactName($booking);
        $rows = $this->bookingDetailRows($booking, [
            ['label' => 'PNR', 'value' => ModernEmailLayout::customerPnrDisplay($booking->pnr)],
        ]);
        $parts = [];
        if ($hasPdfAttachment) {
            $parts[] = '<p style="margin:0 0 12px;">Your itinerary PDF is attached to this email.</p>';
        } else {
            $parts[] = '<p style="margin:0 0 12px;">Please check your booking account for full itinerary details.</p>';
        }
        $ticketHtml = $this->ticketNumbersHtml($booking);
        if ($ticketHtml !== '') {
            $parts[] = $ticketHtml;
        }
        if ($staffNote !== null && trim($staffNote) !== '') {
            $parts[] = '<p style="margin:12px 0 0;"><strong>Note from our team:</strong> '.e(trim($staffNote)).'</p>';
        }
        $cta = $this->customerBookingCta($booking);

        return $this->render(
            profile: $profile,
            headline: 'Your ticket itinerary is ready',
            intro: sprintf('Hello %s, your ticket itinerary for booking %s is ready.', $name, $booking->reference_code),
            contentHtml: implode('', $parts) !== '' ? implode('', $parts) : null,
            details: $rows,
            ctaUrl: $cta['url'] ?? null,
            ctaLabel: $cta['label'] ?? null,
            statusBannerLabel: 'Itinerary ready',
            statusBannerTone: 'success',
            nextSteps: [
                $hasPdfAttachment
                    ? 'Your itinerary PDF is attached to this email.'
                    : 'Check your booking account for full itinerary details.',
                'Contact support if you have questions about your itinerary.',
            ],
            footerDisclaimer: 'Please keep this email for your records.',
            plainBody: $this->plainLines(array_merge(
                ['Hello '.$name.',', '', 'Your ticket itinerary is ready.', 'Reference: '.$booking->reference_code],
                $hasPdfAttachment ? ['The itinerary PDF is attached to this email.'] : [],
                $staffNote !== null && trim($staffNote) !== '' ? ['Note from our team: '.trim($staffNote)] : [],
            )),
        );
    }

    public function googleCustomerWelcome(User $user, string $dashboardUrl): CustomerFacingEmailRendered
    {
        $agency = $user->currentAgency;
        $profile = CompanyEmailProfileResolver::resolve($agency);
        $name = trim((string) ($user->name ?? 'Customer')) ?: 'Customer';

        return $this->render(
            profile: $profile,
            headline: 'Welcome to '.$profile->name,
            intro: sprintf('Hello %s, your customer account is ready. You can sign in with Google and manage bookings from your account.', $name),
            contentHtml: null,
            details: [
                ['label' => 'Name', 'value' => $name],
                ['label' => 'Email', 'value' => (string) $user->email],
            ],
            ctaUrl: $dashboardUrl,
            ctaLabel: 'Go to my account',
            statusBannerLabel: 'Account ready',
            statusBannerTone: 'success',
            nextSteps: [
                'Sign in to manage your bookings and profile.',
                'Contact support if you did not create this account.',
            ],
            footerDisclaimer: 'Please keep this email for your records.',
            plainBody: $this->plainLines([
                'Hello '.$name.',',
                '',
                'Your customer account is ready.',
                'Sign in: '.$dashboardUrl,
            ]),
        );
    }

    protected function render(
        CompanyEmailProfile $profile,
        string $headline,
        string $intro,
        ?string $contentHtml,
        array $details,
        ?string $ctaUrl,
        ?string $ctaLabel,
        string $footerDisclaimer,
        string $plainBody,
        ?string $statusBannerLabel = null,
        string $statusBannerTone = 'info',
        array $nextSteps = [],
    ): CustomerFacingEmailRendered {
        $html = View::make('emails.layouts.modern', array_merge(
            ['companyEmailProfile' => $profile],
            ModernEmailLayout::viewData([
                'emailMode' => ModernEmailLayout::MODE_CUSTOMER,
                'headline' => $headline,
                'intro' => $intro,
                'statusBannerLabel' => $statusBannerLabel ?? $headline,
                'statusBannerTone' => $statusBannerTone,
                'contentHtml' => $contentHtml ?? '',
                'details' => $details,
                'ctaUrl' => $ctaUrl,
                'ctaLabel' => $ctaLabel,
                'footerDisclaimer' => $footerDisclaimer,
                'nextSteps' => $nextSteps,
            ]),
        ))->render();

        return new CustomerFacingEmailRendered(
            html: $html,
            plainBody: $plainBody,
            profile: $profile,
        );
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    protected function bookingDetailRows(Booking $booking, array $extra = []): array
    {
        $rows = [
            ['label' => 'Booking reference', 'value' => e((string) $booking->reference_code)],
            ['label' => 'Route', 'value' => e(trim((string) ($booking->route ?? '')) !== '' ? (string) $booking->route : EmailPlaceholderFallbacks::fallbackFor('route'))],
            ['label' => 'Travel date', 'value' => e($booking->travel_date?->format('d M Y') ?? EmailPlaceholderFallbacks::fallbackFor('travel_date'))],
            ['label' => 'Passenger', 'value' => e($this->contactName($booking))],
            ['label' => 'Booking status', 'value' => e($this->formatBookingStatus($booking))],
        ];

        $paymentStatus = trim((string) ($booking->payment_status ?? ''));
        if ($paymentStatus !== '') {
            $rows[] = ['label' => 'Payment status', 'value' => e($this->formatStatusLabel($paymentStatus))];
        }

        return array_merge($rows, array_map(
            fn (array $row): array => [
                'label' => $row['label'],
                'value' => e((string) ($row['value'] ?? '')),
            ],
            $extra,
        ));
    }

    protected function contactName(Booking $booking): string
    {
        $fromContact = trim((string) ($booking->contact?->meta['name'] ?? ''));
        if ($fromContact !== '') {
            return $fromContact;
        }

        $fromCustomer = trim((string) ($booking->customer?->name ?? ''));

        return $fromCustomer !== '' ? $fromCustomer : 'Customer';
    }

    /**
     * @return array{url: string, label: string}|null
     */
    protected function customerBookingCta(Booking $booking): ?array
    {
        if ($booking->customer_id !== null && Route::has('customer.bookings.show')) {
            return [
                'url' => route('customer.bookings.show', $booking, absolute: true),
                'label' => 'View booking',
            ];
        }

        if ($booking->customer_id !== null && Route::has('customer.bookings.index')) {
            return [
                'url' => route('customer.bookings.index', absolute: true),
                'label' => 'My bookings',
            ];
        }

        if (Route::has('booking.lookup')) {
            return [
                'url' => route('booking.lookup', absolute: true),
                'label' => 'Look up booking',
            ];
        }

        return null;
    }

    protected function formatBookingStatus(Booking $booking): string
    {
        return $this->formatStatusLabel($booking->status->value);
    }

    protected function formatStatusLabel(string $value): string
    {
        return Str::headline(str_replace('_', ' ', $value));
    }

    protected function passengerListHtml(Booking $booking): string
    {
        $passengers = $booking->passengers ?? collect();
        if ($passengers->isEmpty()) {
            return '';
        }

        $lines = $passengers->take(6)->map(function ($p): string {
            return trim((string) ($p->title ?? '').' '.(string) $p->first_name.' '.(string) $p->last_name);
        })->filter()->values();

        if ($lines->isEmpty()) {
            return '';
        }

        $html = '<p style="margin:0 0 8px;font-weight:600;color:#334155;">Passengers</p><ul style="margin:0;padding-left:20px;color:#334155;">';
        foreach ($lines as $line) {
            $html .= '<li>'.e($line).'</li>';
        }
        if ($passengers->count() > 6) {
            $html .= '<li>…and '.($passengers->count() - 6).' more</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    protected function ticketNumbersHtml(Booking $booking): string
    {
        if ($booking->tickets->isEmpty()) {
            return '';
        }

        $html = '<p style="margin:0 0 8px;font-weight:600;color:#334155;">Ticket numbers</p><ul style="margin:0;padding-left:20px;color:#334155;">';
        foreach ($booking->tickets as $ticket) {
            $line = e((string) ($ticket->ticket_number ?? 'N/A'));
            $passenger = trim((string) ($ticket->meta['passenger_name'] ?? ''));
            if ($passenger !== '') {
                $line .= ' <span style="color:#64748b;">('.e($passenger).')</span>';
            }
            $html .= '<li>'.$line.'</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    /**
     * @return list<string>
     */
    protected function ticketPlainLines(Booking $booking): array
    {
        if ($booking->tickets->isEmpty()) {
            return [];
        }

        $lines = ['Ticket numbers:'];
        foreach ($booking->tickets as $ticket) {
            $lines[] = '- '.(string) ($ticket->ticket_number ?? 'N/A');
        }

        return $lines;
    }

    /**
     * @param  list<string>  $lines
     */
    protected function plainLines(array $lines): string
    {
        return implode("\n", $lines);
    }
}
