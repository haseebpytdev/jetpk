<?php

namespace App\Support\Sabre\Scenario;

use App\Models\Booking;

/**
 * Post-cancel retrieve identity: booking → supplier booking PNR only (allows pending local cancel state).
 */
final class SabreGdsQrUnticketedPostCancelRetrieveIdentityResolver
{
    public function __construct(
        private readonly SabreGdsQrUnticketedCancelIdentityResolver $cancelIdentityResolver,
        private readonly SabreGdsQrUnticketedPostCancelPriorCancellationGate $priorCancellationGate,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(Booking $booking, ?int $expectedSupplierBookingId, string $priorLifecycleRunId, bool $productionSend): array
    {
        $base = $this->cancelIdentityResolver->resolve($booking, $expectedSupplierBookingId);
        $blockers = is_array($base['identity_blockers'] ?? null) ? $base['identity_blockers'] : [];

        foreach (['booking_already_cancelled', 'supplier_booking_already_cancelled'] as $allowedPending) {
            $blockers = array_values(array_filter($blockers, static fn (string $b): bool => $b !== $allowedPending));
        }

        $prior = $this->priorCancellationGate->evaluate(
            $booking,
            $priorLifecycleRunId,
            is_string($base['locator_sha256'] ?? null) ? (string) $base['locator_sha256'] : null,
            $productionSend,
        );
        foreach ($prior['blockers'] ?? [] as $priorBlocker) {
            $blockers[] = (string) $priorBlocker;
        }

        return array_merge($base, [
            'identity_blockers' => $blockers,
            'identity_checks_passed' => $blockers === [],
            'prior_cancellation' => $prior,
            'prior_cancellation_confirmed' => ($prior['prior_cancellation_confirmed'] ?? false) === true,
            'prior_cancellation_ambiguous' => ($prior['prior_cancellation_ambiguous'] ?? false) === true,
        ]);
    }
}
