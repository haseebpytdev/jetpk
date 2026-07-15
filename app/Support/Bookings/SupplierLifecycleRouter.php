<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;

/**
 * Routes booking lifecycle decisions to supplier-scoped handlers (no cross-supplier rule mixing).
 */
final class SupplierLifecycleRouter
{
    public function __construct(
        private readonly SupplierLifecycleContextResolver $contextResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function context(Booking $booking): array
    {
        return $this->contextResolver->resolve($booking);
    }

    /**
     * @param  array<string, mixed>|null  $checkoutOutcome
     * @return array{scope: string, provider: string, notice: string, reason_code: string|null, handler_key: string}|null
     */
    public function confirmationNotice(Booking $booking, ?array $checkoutOutcome = null, ?string $legacySessionNotice = null): ?array
    {
        $ctx = $this->context($booking);

        return match ($ctx['handler_key']) {
            SupplierLifecycleContextResolver::HANDLER_SABRE_GDS => $this->wrapNotice(
                $ctx,
                BookingSupplierConfirmationNoticeResolver::resolveSabreGdsNotice($booking, $checkoutOutcome, $legacySessionNotice),
            ),
            SupplierLifecycleContextResolver::HANDLER_SABRE_NDC => $this->wrapNotice(
                $ctx,
                BookingSupplierConfirmationNoticeResolver::resolveSabreNdcNotice($booking, $checkoutOutcome, $legacySessionNotice),
            ),
            SupplierLifecycleContextResolver::HANDLER_PIA_NDC => $this->wrapPiaNotice($ctx, $booking, $legacySessionNotice),
            default => $this->resolveGenericNotice($ctx, $legacySessionNotice),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function adminLifecycleSummary(Booking $booking): array
    {
        $ctx = $this->context($booking);

        return match ($ctx['handler_key']) {
            SupplierLifecycleContextResolver::HANDLER_SABRE_GDS => array_merge(
                ['handler_key' => $ctx['handler_key'], 'display_label' => $ctx['display_label'], 'supported' => true],
                app(SabreGdsAutoPnrLifecycleService::class)->resolveForAdmin($booking),
                ['lifecycle_mode' => 'sabre_gds_pnr_ticketing'],
            ),
            SupplierLifecycleContextResolver::HANDLER_SABRE_NDC => [
                'handler_key' => $ctx['handler_key'],
                'display_label' => $ctx['display_label'],
                'supported' => true,
                'lifecycle_mode' => 'sabre_ndc_order',
                'order_reference' => trim((string) ($booking->supplier_reference ?? '')),
                'ticketing_pending' => in_array((string) ($booking->ticketing_status ?? ''), ['pending', ''], true),
            ],
            SupplierLifecycleContextResolver::HANDLER_PIA_NDC => [
                'handler_key' => $ctx['handler_key'],
                'display_label' => $ctx['display_label'],
                'supported' => true,
                'lifecycle_mode' => 'pia_ndc_order',
                'pnr' => trim((string) ($booking->pnr ?? '')),
                'order_id' => trim((string) data_get($this->contextResolver->meta($booking), 'pia_ndc_context.order_id', '')),
            ],
            SupplierLifecycleContextResolver::HANDLER_AIRBLUE,
            SupplierLifecycleContextResolver::HANDLER_AIRSIAL,
            SupplierLifecycleContextResolver::HANDLER_DUFFEL => [
                'handler_key' => $ctx['handler_key'],
                'display_label' => $ctx['display_label'],
                'supported' => true,
                'lifecycle_mode' => 'supplier_native',
                'supplier_booking_status' => (string) ($booking->supplier_booking_status ?? ''),
            ],
            default => $this->unsupportedManualState($ctx, 'admin_lifecycle'),
        };
    }

    /**
     * @return array{supported: bool, state: string, handler_key: string, display_label: string, message: string}
     */
    public function unsupportedManualState(array $context, string $feature = 'lifecycle'): array
    {
        $label = (string) ($context['display_label'] ?? 'Unknown supplier');

        return [
            'supported' => false,
            'state' => 'manual_action_required',
            'handler_key' => (string) ($context['handler_key'] ?? SupplierLifecycleContextResolver::HANDLER_OTHER),
            'display_label' => $label,
            'message' => "Supplier {$feature} is not automated for {$label}. Handle manually.",
        ];
    }

    public function isHandler(Booking $booking, string $handlerKey): bool
    {
        return $this->contextResolver->isHandler($booking, $handlerKey);
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array{notice: string, reason_code: string|null}|null  $resolved
     * @return array{scope: string, provider: string, notice: string, reason_code: string|null, handler_key: string}|null
     */
    protected function wrapNotice(array $ctx, ?array $resolved): ?array
    {
        if ($resolved === null) {
            return null;
        }

        return [
            'scope' => (string) $ctx['handler_key'],
            'provider' => (string) $ctx['supplier_provider'],
            'handler_key' => (string) $ctx['handler_key'],
            'notice' => $resolved['notice'],
            'reason_code' => $resolved['reason_code'],
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{scope: string, provider: string, notice: string, reason_code: string|null, handler_key: string}|null
     */
    protected function wrapPiaNotice(array $ctx, Booking $booking, ?string $legacySessionNotice): ?array
    {
        $notice = BookingSupplierConfirmationNoticeResolver::resolvePiaNdcNotice($booking, $legacySessionNotice);
        if ($notice === null) {
            return null;
        }

        return [
            'scope' => (string) $ctx['handler_key'],
            'provider' => SupplierProvider::PiaNdc->value,
            'handler_key' => (string) $ctx['handler_key'],
            'notice' => $notice,
            'reason_code' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{scope: string, provider: string, notice: string, reason_code: string|null, handler_key: string}|null
     */
    protected function resolveGenericNotice(array $ctx, ?string $legacySessionNotice): ?array
    {
        if ($legacySessionNotice === null || trim($legacySessionNotice) === '') {
            return null;
        }

        if (BookingSupplierConfirmationNoticeResolver::looksLikeSabreNotice($legacySessionNotice)) {
            return null;
        }

        return [
            'scope' => (string) $ctx['handler_key'],
            'provider' => (string) $ctx['supplier_provider'],
            'handler_key' => (string) $ctx['handler_key'],
            'notice' => trim($legacySessionNotice),
            'reason_code' => null,
        ];
    }
}
