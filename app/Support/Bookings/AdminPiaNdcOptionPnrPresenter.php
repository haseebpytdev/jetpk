<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use Carbon\Carbon;

/**
 * Admin booking detail: PIA NDC option PNR status panel (R12G/R12H — auto-create only).
 */
final class AdminPiaNdcOptionPnrPresenter
{
    public function __construct(
        private readonly PiaNdcBookingProviderContextResolver $providerContextResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function panel(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== SupplierProvider::PiaNdc->value) {
            return ['show' => false];
        }

        $resolved = $this->providerContextResolver->resolve($booking);
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $autoState = is_array($meta['pia_ndc_auto_option_pnr'] ?? null) ? $meta['pia_ndc_auto_option_pnr'] : [];
        $latestAttempt = $this->latestCreateAttempt($booking);
        $paymentTimeLimit = trim((string) (
            $context['payment_time_limit']
            ?? $booking->payment_required_by?->toIso8601String()
            ?? $booking->pnr_expires_at?->toIso8601String()
            ?? ''
        ));

        return [
            'show' => true,
            'title' => 'PIA NDC option PNR',
            'can_create' => false,
            'helper_text' => 'PIA NDC option PNR is created automatically when the customer submits the booking request. Ticketing remains payment-gated.',
            'provider_context_source' => $resolved['source'] !== '' ? $resolved['source'] : null,
            'pnr' => trim((string) ($booking->pnr ?? '')) !== '' ? (string) $booking->pnr : null,
            'order_id' => trim((string) ($context['order_id'] ?? $booking->supplier_reference ?? '')) ?: null,
            'airline_locator' => trim((string) ($context['airline_locator'] ?? '')) ?: null,
            'supplier_booking_status' => trim((string) ($booking->supplier_booking_status ?? '')) ?: null,
            'payment_required_by' => $this->formatTimestamp($paymentTimeLimit),
            'owner_code' => trim((string) ($context['owner_code'] ?? $resolved['context']['owner_code'] ?? '')) ?: null,
            'order_status' => trim((string) ($context['order_status'] ?? '')) ?: null,
            'latest_attempt_status' => $latestAttempt?->status,
            'latest_attempt_action' => $latestAttempt?->action,
            'latest_safe_error' => $this->latestSafeError($latestAttempt, $autoState),
            'auto_option_pnr_status' => (string) ($autoState['status'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $autoState
     */
    private function latestSafeError(?SupplierBookingAttempt $attempt, array $autoState): ?string
    {
        if ($attempt !== null && in_array((string) $attempt->status, ['failed', 'pending'], true)) {
            $message = trim((string) ($attempt->error_message ?? ''));
            if ($message !== '') {
                return $message;
            }
        }

        if (($autoState['status'] ?? '') === 'failed') {
            $message = trim((string) ($autoState['safe_message'] ?? ''));

            return $message !== '' ? $message : 'Automatic PIA NDC option PNR creation failed.';
        }

        return null;
    }

    private function latestCreateAttempt(Booking $booking): ?SupplierBookingAttempt
    {
        return SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::PiaNdc->value)
            ->whereIn('action', ['create_option_pnr', 'auto_create_option_pnr'])
            ->orderByDesc('id')
            ->first();
    }

    private function formatTimestamp(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDayDateTimeString();
        } catch (\Throwable) {
            return $value;
        }
    }
}
