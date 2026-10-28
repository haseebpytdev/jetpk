<?php

namespace App\Services\Suppliers\AirBlue;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueCancellationException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueUnavailableException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueXmlException;
use Illuminate\Support\Facades\Log;

class AirBlueCancelService
{
    public function __construct(
        private readonly AirBlueClient $client,
        private readonly AirBlueConfigResolver $configResolver,
        private readonly AirBlueOtaXmlBuilder $otaXmlBuilder,
        private readonly AirBlueOtaResponseNormalizer $otaNormalizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function cancelForBooking(Booking $booking, SupplierConnection $connection, User $actor): array
    {
        unset($actor);

        return $this->cancelOta($booking, $connection);
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(Booking $booking, SupplierConnection $connection): array
    {
        throw new AirBlueCancellationException(
            'cancel_preview_unsupported',
            422,
            'Zapways OTA does not support a separate cancellation preview step.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function commit(Booking $booking, SupplierConnection $connection): array
    {
        return $this->cancelOta($booking, $connection);
    }

    /**
     * @return array<string, mixed>
     */
    private function cancelOta(Booking $booking, SupplierConnection $connection): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];

        if (($context['cancel_committed'] ?? false) === true) {
            throw new AirBlueCancellationException('duplicate_cancellation_guard', 409, 'Cancellation already committed.');
        }

        if (($context['cancellation_outcome'] ?? '') === 'uncertain') {
            throw new AirBlueCancellationException(
                'cancellation_outcome_uncertain',
                409,
                'Prior cancellation mutation is uncertain; reconcile manually — do not retry Cancel.',
            );
        }

        if ($this->isAlreadyRefunded($context)) {
            throw new AirBlueCancellationException(
                'refunded_booking_cancel_blocked',
                422,
                'Provider refund is already confirmed; unticketed Cancel is not applicable.',
            );
        }

        if ($this->isTicketed($context)) {
            throw new AirBlueCancellationException(
                'ticketed_booking_requires_refund',
                422,
                'Ticketed AirBlue bookings must use the refund workflow rather than unticketed cancellation.',
            );
        }

        $pnr = trim((string) ($context['pnr'] ?? $booking->pnr ?? $booking->supplier_reference ?? ''));
        $instance = trim((string) ($context['instance'] ?? ''));
        if ($pnr === '' || $instance === '') {
            throw new AirBlueCancellationException('missing_order_context', 422, 'Cancellation failed, admin review required.');
        }

        $config = $this->configResolver->resolveOta($connection);
        $xml = $this->otaXmlBuilder->buildCancelRequest($config, $pnr, $instance);

        $enteredProviderCall = false;

        try {
            $enteredProviderCall = true;
            $response = $this->client->callOta($connection, 'cancel', $xml, [
                'booking_id' => $booking->id,
                'request_context' => 'cancel',
            ]);
            $result = $this->otaNormalizer->normalizeCancelResponse($response);
            $context['cancel_committed'] = true;
            $context['cancellation_status'] = $result['cancellation_status'] ?? 'cancelled';
            $context['cancellation_outcome'] = 'cancelled';
            unset($context['supplier_outcome_unknown'], $context['requires_retrieve_before_retry']);
            $meta['airblue_context'] = $context;
            $booking->meta = $meta;
            $booking->save();

            return array_merge($result, ['success' => true, 'status' => 'cancelled']);
        } catch (AirBlueUnavailableException|AirBlueXmlException $exception) {
            if (AirBlueAmbiguousMutationClassifier::requestMayHaveBeenSent($exception)) {
                $this->persistUncertain($booking, $context);

                throw new AirBlueCancellationException(
                    $exception->normalizedCode !== '' ? $exception->normalizedCode : 'cancellation_outcome_uncertain',
                    $exception->httpStatus,
                    'Cancellation mutation is uncertain; reconcile manually — do not retry Cancel.',
                    $exception->context,
                    $exception,
                );
            }

            throw new AirBlueCancellationException(
                $exception->normalizedCode,
                $exception->httpStatus,
                'Cancellation failed, admin review required.',
                $exception->context,
                $exception,
            );
        } catch (AirBlueException $exception) {
            $code = (string) $exception->normalizedCode;
            $transmitted = AirBlueAmbiguousMutationClassifier::requestMayHaveBeenSent($exception);
            if (AirBlueAmbiguousMutationClassifier::isUncertainAfterTransmission($transmitted, $code)) {
                $this->persistUncertain($booking, $context);

                throw new AirBlueCancellationException(
                    $code,
                    $exception->httpStatus,
                    'Cancellation mutation is uncertain; reconcile manually — do not retry Cancel.',
                    $exception->context,
                    $exception,
                );
            }

            throw new AirBlueCancellationException(
                $exception->normalizedCode,
                $exception->httpStatus,
                'Cancellation failed, admin review required.',
                $exception->context,
                $exception,
            );
        } catch (\Throwable $exception) {
            Log::channel('air-blue')->warning('airblue.cancel.unexpected', [
                'booking_id' => $booking->id,
                'exception' => $exception::class,
                'entered_provider_call' => $enteredProviderCall,
            ]);

            if ($enteredProviderCall) {
                $this->persistUncertain($booking, $context);

                throw new AirBlueCancellationException(
                    'cancellation_unexpected',
                    500,
                    'Cancellation mutation is uncertain; reconcile manually — do not retry Cancel.',
                    ['booking_id' => $booking->id],
                    $exception,
                );
            }

            throw new AirBlueCancellationException(
                'cancellation_unexpected',
                500,
                'Cancellation failed, admin review required.',
                ['booking_id' => $booking->id],
                $exception,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function isTicketed(array $context): bool
    {
        if (($context['ticketing_status'] ?? '') === 'ticketed') {
            return true;
        }

        if (is_array($context['ticket_numbers'] ?? null) && $context['ticket_numbers'] !== []) {
            return true;
        }

        $records = is_array($context['ticketing_records'] ?? null) ? $context['ticketing_records'] : [];
        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }
            $doc = trim((string) ($record['ticket_document_number'] ?? $record['ticket_number'] ?? ''));
            $status = strtoupper(trim((string) ($record['ticketing_status'] ?? '')));
            if ($doc !== '' && $status !== 'REFUNDED') {
                return true;
            }
            if (in_array($status, ['OK', 'ISSUED', 'TICKETED', 'OPEN'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function isAlreadyRefunded(array $context): bool
    {
        return ($context['refund_status'] ?? '') === 'refunded';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function persistUncertain(Booking $booking, array $context): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $existing = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        $merged = array_merge($existing, $context);
        $merged['cancellation_outcome'] = 'uncertain';
        $merged['cancellation_status'] = 'pending_reconciliation';
        $merged['supplier_outcome_unknown'] = true;
        $meta['airblue_context'] = $merged;
        $booking->meta = $meta;
        $booking->save();
    }
}
