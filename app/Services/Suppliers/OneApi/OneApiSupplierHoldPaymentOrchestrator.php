<?php

namespace App\Services\Suppliers\OneApi;

use App\Data\SupplierHoldPaymentResultData;
use App\Enums\BookingCommunicationEvent;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Communication\BookingCommunicationService;
use App\Services\Suppliers\OneApi\Reservation\OneApiHoldPaymentService;
use App\Services\Suppliers\OneApi\Reservation\OneApiRetrieveService;
use App\Services\Suppliers\OneApi\Support\OneApiBookResponseInterpreter;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates One API hold payment: modify, confirm read, persist, communicate once.
 */
class OneApiSupplierHoldPaymentOrchestrator
{
    public function __construct(
        private readonly OneApiHoldPaymentService $holdPaymentService,
        private readonly OneApiRetrieveService $retrieveService,
        private readonly BookingCommunicationService $communicationService,
    ) {}

    /**
     * @param  array<string, mixed>  $diagnosticContext
     */
    public function payHeldReservation(
        Booking $booking,
        SupplierConnection $connection,
        User $actor,
        array $diagnosticContext = [],
    ): SupplierHoldPaymentResultData {
        unset($actor);

        if ($connection->provider !== SupplierProvider::OneApi) {
            return new SupplierHoldPaymentResultData(false, 'provider_mismatch', 'Provider mismatch.');
        }

        $existing = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::OneApi->value)
            ->where('action', 'hold_payment')
            ->where('status', 'success')
            ->first();
        if ($existing !== null) {
            return new SupplierHoldPaymentResultData(true, 'already_paid', 'Hold payment already completed.');
        }

        if (! $this->holdPaymentService->canPayHeldReservation($booking, $connection)) {
            return new SupplierHoldPaymentResultData(false, 'payment_rejected', 'Hold payment is not allowed for this booking.');
        }

        $ambiguous = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::OneApi->value)
            ->where('action', 'hold_payment')
            ->where('status', 'ambiguous')
            ->exists();
        if ($ambiguous) {
            return new SupplierHoldPaymentResultData(false, 'reconciliation_required', 'Hold payment is ambiguous; reconcile before retrying.');
        }

        return DB::transaction(function () use ($booking, $connection, $diagnosticContext): SupplierHoldPaymentResultData {
            $attempt = SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $connection->id,
                'provider' => SupplierProvider::OneApi->value,
                'action' => 'hold_payment',
                'status' => 'in_progress',
                'attempted_at' => now(),
            ]);

            $modify = $this->holdPaymentService->payHeldReservation($booking, $connection, $diagnosticContext);
            if (($modify['soap_fault'] ?? null) !== null || ($modify['errors'] ?? []) !== []) {
                $attempt->forceFill(['status' => 'failed', 'completed_at' => now()])->save();

                return new SupplierHoldPaymentResultData(false, 'modify_failed', 'Modification failed.');
            }

            $modifyInterpreted = OneApiBookResponseInterpreter::fromParsed($modify);
            if ($modifyInterpreted->isAmbiguous) {
                $attempt->forceFill(['status' => 'ambiguous', 'completed_at' => now()])->save();

                return new SupplierHoldPaymentResultData(false, 'reconciliation_required', 'Modification outcome ambiguous.');
            }

            $readContext = $diagnosticContext;
            $confirmRead = $diagnosticContext['fixture_paths']['read_after_modify']
                ?? $diagnosticContext['fixture_paths']['read_after_hold_paid']
                ?? null;
            if (is_string($confirmRead) && $confirmRead !== '') {
                $paths = is_array($readContext['fixture_paths'] ?? null) ? $readContext['fixture_paths'] : [];
                $paths['read'] = $confirmRead;
                $readContext['fixture_paths'] = $paths;
                $readContext['fixture_path'] = $confirmRead;
            }

            $read = $this->retrieveService->getReservationByPnr(
                $connection,
                (string) $booking->pnr,
                'hold-pay-confirm:'.$booking->id,
                $readContext,
            );
            $readInterpreted = OneApiBookResponseInterpreter::fromParsed($read);
            if (! $readInterpreted->isTicketed) {
                $attempt->forceFill(['status' => 'failed', 'completed_at' => now()])->save();

                return new SupplierHoldPaymentResultData(false, 'not_ticketed', 'Reservation is not ticketed after modification.');
            }

            $meta = is_array($booking->meta) ? $booking->meta : [];
            $meta['one_api_hold_payment'] = [
                'paid_at' => now()->toIso8601String(),
                'transaction_identifier' => $readInterpreted->transactionIdentifier,
            ];
            $booking->forceFill([
                'supplier_booking_status' => 'ticketed',
                'ticketing_status' => 'ticketed',
                'meta' => $meta,
            ])->save();

            $attempt->forceFill([
                'status' => 'success',
                'response_payload' => ['ticketing_status' => $readInterpreted->ticketingStatus],
                'completed_at' => now(),
            ])->save();

            $this->communicationService->sendTicketIssued($booking->fresh());

            return new SupplierHoldPaymentResultData(true, 'success', 'Hold payment completed.');
        });
    }
}
