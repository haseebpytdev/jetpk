<?php

namespace App\Services\Suppliers\AirBlue;

use App\Data\RefundResultData;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueUnavailableException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueXmlException;
use Illuminate\Support\Facades\Log;

/**
 * Zapways OTA v3 full refund (AirBookModify ModificationType=1).
 *
 * Distinct from void (unsupported) and Cancel UniqueID Type=14.
 * Fail-closed behind credentials.refund_enabled. Never retries Type=1 after transmission.
 */
class AirBlueRefundService
{
    public function __construct(
        private readonly AirBlueClient $client,
        private readonly AirBlueConfigResolver $configResolver,
        private readonly AirBlueOtaXmlBuilder $otaXmlBuilder,
        private readonly AirBlueOtaResponseNormalizer $otaNormalizer,
        private readonly AirBlueProtocolGuard $protocolGuard,
    ) {}

    public function refundFull(Booking $booking, SupplierConnection $connection, User $actor): RefundResultData
    {
        unset($actor);

        if ($this->isAlreadyRefunded($booking)) {
            return new RefundResultData(
                success: false,
                status: 'duplicate_refund_guard',
                provider: SupplierProvider::Airblue->value,
                outcome: 'DUPLICATE',
                error_code: 'duplicate_refund_guard',
                error_message: 'Provider refund is already confirmed for this booking.',
                provider_calls: 0,
            );
        }

        if ($this->isRefundUncertain($booking)) {
            return new RefundResultData(
                success: false,
                status: 'uncertain',
                provider: SupplierProvider::Airblue->value,
                outcome: 'UNCERTAIN_AFTER_TRANSMISSION',
                error_code: 'refund_outcome_uncertain',
                error_message: 'Prior refund mutation is uncertain; reconcile with Read only — do not retry Type=1.',
                provider_calls: 0,
                safe_summary: ['refund_outcome' => 'UNCERTAIN'],
            );
        }

        if (! $this->isRefundFeatureEnabled($connection)) {
            return new RefundResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Airblue->value,
                outcome: 'DEFINITIVE_PROVIDER_REJECTION',
                error_code: 'refund_feature_disabled',
                error_message: 'AirBlue full refund is not enabled for this connection.',
                provider_calls: 0,
            );
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];

        if (! $this->isTicketed($context, $booking)) {
            return new RefundResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Airblue->value,
                outcome: 'DEFINITIVE_PROVIDER_REJECTION',
                error_code: 'booking_not_ticketed',
                error_message: 'Full refund requires an already-ticketed AirBlue booking.',
                provider_calls: 0,
            );
        }

        $pnr = trim((string) ($context['pnr'] ?? $booking->pnr ?? $booking->supplier_reference ?? ''));
        if ($pnr === '') {
            return new RefundResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Airblue->value,
                outcome: 'DEFINITIVE_PROVIDER_REJECTION',
                error_code: 'missing_order_context',
                error_message: 'Full refund failed, admin review required.',
                provider_calls: 0,
            );
        }

        $transmitted = false;

        try {
            $this->protocolGuard->assertCompatible($connection, $context, 'refund');
            $config = $this->configResolver->resolveOta($connection);
            $xml = $this->otaXmlBuilder->buildAirBookModifyRequest($config, [
                'pnr' => $pnr,
                'modification_type' => '1',
            ]);

            $transmitted = true;
            $response = $this->client->callOta($connection, 'air_book_modify', $xml, [
                'booking_id' => $booking->id,
                'request_context' => 'air_book_modify_full_refund',
            ]);

            $normalized = $this->otaNormalizer->normalizeRefundResponse($response, $context);
            $this->persistRefund($booking, $normalized, false);

            $refundStatus = (string) ($normalized['refund_status'] ?? 'failed');
            $success = $refundStatus === 'refunded';

            return new RefundResultData(
                success: $success,
                status: $refundStatus,
                provider: SupplierProvider::Airblue->value,
                outcome: $success ? 'DEFINITIVE_SUCCESS' : 'DEFINITIVE_PROVIDER_REJECTION',
                provider_calls: 1,
                safe_summary: [
                    'refunded_ticket_count' => (int) ($normalized['refunded_ticket_count'] ?? 0),
                    'refund_transaction_amount_present' => ($normalized['refund_transaction_amount'] ?? null) !== null,
                    'seats_refunded' => (bool) ($normalized['seats_refunded'] ?? false),
                    'items_refunded' => (bool) ($normalized['items_refunded'] ?? false),
                ],
                error_code: $success ? null : 'refund_not_confirmed',
                error_message: $success ? null : 'Provider did not confirm full coupon refund.',
            );
        } catch (AirBlueUnavailableException $exception) {
            if ($transmitted) {
                $this->persistUncertain($booking, $context);

                return new RefundResultData(
                    success: false,
                    status: 'uncertain',
                    provider: SupplierProvider::Airblue->value,
                    outcome: 'UNCERTAIN_AFTER_TRANSMISSION',
                    error_code: $exception->normalizedCode,
                    error_message: $exception->safeMessage,
                    provider_calls: 1,
                    safe_summary: array_merge(
                        ['refund_outcome' => 'UNCERTAIN'],
                        $this->safeProviderErrorSummary($exception, $booking),
                    ),
                );
            }

            return new RefundResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Airblue->value,
                outcome: 'DEFINITIVE_PROVIDER_REJECTION',
                error_code: $exception->normalizedCode,
                error_message: $exception->safeMessage,
                provider_calls: 0,
                safe_summary: $this->safeProviderErrorSummary($exception, $booking),
            );
        } catch (AirBlueXmlException $exception) {
            if ($transmitted) {
                $this->persistUncertain($booking, $context);

                return new RefundResultData(
                    success: false,
                    status: 'uncertain',
                    provider: SupplierProvider::Airblue->value,
                    outcome: 'UNCERTAIN_AFTER_TRANSMISSION',
                    error_code: $exception->normalizedCode,
                    error_message: $exception->safeMessage,
                    provider_calls: 1,
                    safe_summary: ['refund_outcome' => 'UNCERTAIN'],
                );
            }

            return new RefundResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Airblue->value,
                outcome: 'DEFINITIVE_PROVIDER_REJECTION',
                error_code: $exception->normalizedCode,
                error_message: $exception->safeMessage,
                provider_calls: 0,
            );
        } catch (AirBlueException $exception) {
            // OTA Error/@Code after HTTP 200 is definitive. Transport/HTTP/SOAP-fault after
            // send is ambiguous — persist uncertain and never auto-retry Type=1.
            $code = (string) $exception->normalizedCode;
            if (AirBlueAmbiguousMutationClassifier::isUncertainAfterTransmission($transmitted, $code)) {
                $this->persistUncertain($booking, $context);

                return new RefundResultData(
                    success: false,
                    status: 'uncertain',
                    provider: SupplierProvider::Airblue->value,
                    outcome: 'UNCERTAIN_AFTER_TRANSMISSION',
                    error_code: $code,
                    error_message: $exception->safeMessage,
                    provider_calls: 1,
                    safe_summary: array_merge(
                        ['refund_outcome' => 'UNCERTAIN'],
                        $this->safeProviderErrorSummary($exception, $booking),
                    ),
                );
            }

            return new RefundResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Airblue->value,
                outcome: 'DEFINITIVE_PROVIDER_REJECTION',
                error_code: $code,
                error_message: $exception->safeMessage,
                provider_calls: $transmitted ? 1 : 0,
                safe_summary: $this->safeProviderErrorSummary($exception, $booking),
            );
        } catch (\Throwable $exception) {
            Log::channel('air-blue')->warning('airblue.refund.unexpected', [
                'booking_id' => $booking->id,
                'exception' => $exception::class,
                'transmitted' => $transmitted,
            ]);

            if ($transmitted) {
                $this->persistUncertain($booking, $context);

                return new RefundResultData(
                    success: false,
                    status: 'uncertain',
                    provider: SupplierProvider::Airblue->value,
                    outcome: 'UNCERTAIN_AFTER_TRANSMISSION',
                    error_code: 'refund_unexpected',
                    error_message: 'Refund mutation is uncertain; reconcile with Read only — do not retry Type=1.',
                    provider_calls: 1,
                    safe_summary: ['refund_outcome' => 'UNCERTAIN'],
                );
            }

            return new RefundResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Airblue->value,
                outcome: 'DEFINITIVE_PROVIDER_REJECTION',
                error_code: 'refund_unexpected',
                error_message: 'Full refund failed, admin review required.',
                provider_calls: 0,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function persistRefund(Booking $booking, array $normalized, bool $uncertain): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = array_merge(
            is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [],
            is_array($normalized['provider_context'] ?? null) ? $normalized['provider_context'] : [],
        );
        if ($uncertain) {
            $context['refund_status'] = 'uncertain';
        }
        $meta['airblue_context'] = $context;
        $booking->meta = $meta;
        $booking->save();

        if (! $uncertain && ($normalized['refund_status'] ?? '') === 'refunded') {
            SupplierBooking::query()
                ->where('booking_id', $booking->id)
                ->where('provider', SupplierProvider::Airblue->value)
                ->update(['status' => 'refunded']);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function persistUncertain(Booking $booking, array $context): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $existing = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        $meta['airblue_context'] = array_merge($existing, $context, [
            'refund_status' => 'uncertain',
        ]);
        $booking->meta = $meta;
        $booking->save();
    }

    private function isAlreadyRefunded(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        if (($context['refund_status'] ?? '') === 'refunded') {
            return true;
        }

        $records = is_array($context['ticketing_records'] ?? null) ? $context['ticketing_records'] : [];
        if ($records === []) {
            return false;
        }

        $seen = false;
        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }
            $status = strtoupper(trim((string) ($record['ticketing_status'] ?? '')));
            $doc = trim((string) ($record['ticket_document_number'] ?? ''));
            if ($status === '' && $doc === '') {
                continue;
            }
            $seen = true;
            if ($status !== 'REFUNDED') {
                return false;
            }
        }

        return $seen;
    }

    private function isRefundUncertain(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];

        return ($context['refund_status'] ?? '') === 'uncertain';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function isTicketed(array $context, Booking $booking): bool
    {
        if (($context['ticketing_status'] ?? '') === 'ticketed') {
            return true;
        }
        if (is_array($context['ticket_numbers'] ?? null) && $context['ticket_numbers'] !== []) {
            return true;
        }

        return false;
    }

    private function isRefundFeatureEnabled(SupplierConnection $connection): bool
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];

        return filter_var($credentials['refund_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, mixed>
     */
    private function safeProviderErrorSummary(AirBlueException $exception, Booking $booking): array
    {
        $summary = [];
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        $sensitiveReferences = array_values(array_filter(array_unique([
            trim((string) ($booking->pnr ?? '')),
            trim((string) ($booking->supplier_reference ?? '')),
            trim((string) ($context['pnr'] ?? '')),
            trim((string) ($context['instance'] ?? '')),
        ]), static fn (string $value): bool => strlen($value) >= 3));

        $ticketNumbers = is_array($context['ticket_numbers'] ?? null) ? $context['ticket_numbers'] : [];
        foreach ($ticketNumbers as $ticketNumber) {
            $num = trim((string) $ticketNumber);
            if (strlen($num) >= 3) {
                $sensitiveReferences[] = $num;
            }
        }

        $correlationId = $exception->context['correlation_id'] ?? null;
        if (is_string($correlationId) && trim($correlationId) !== '') {
            $summary['correlation_id'] = substr(trim($correlationId), 0, 80);
        }

        $errors = $exception->context['provider_errors'] ?? null;
        if (! is_array($errors) || $errors === []) {
            return $summary;
        }

        $sanitized = [];
        foreach ($errors as $error) {
            if (! is_array($error)) {
                continue;
            }
            $message = $this->sanitizeProviderErrorMessage(
                (string) ($error['message'] ?? ''),
                $sensitiveReferences,
            );
            $code = substr(trim((string) ($error['code'] ?? '')), 0, 64);
            $type = isset($error['type']) && $error['type'] !== null && $error['type'] !== ''
                ? substr(trim((string) $error['type']), 0, 64)
                : null;
            if ($code === '' && $message === '' && $type === null) {
                continue;
            }
            $sanitized[] = [
                'code' => $code,
                'type' => $type,
                'message' => $message,
            ];
        }

        if ($sanitized !== []) {
            $summary['provider_errors'] = $sanitized;
        }

        return $summary;
    }

    /**
     * @param  list<string>  $sensitiveReferences
     */
    private function sanitizeProviderErrorMessage(string $message, array $sensitiveReferences = []): string
    {
        $message = html_entity_decode($message, ENT_QUOTES | ENT_XML1);
        foreach ($sensitiveReferences as $reference) {
            if ($reference !== '') {
                $message = str_ireplace($reference, '[REDACTED_REFERENCE]', $message);
            }
        }
        $message = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[REDACTED_EMAIL]', $message) ?? $message;
        $message = preg_replace('/(?i)\b(agentpassword|messagepassword|client[_-]?key|client[_-]?id|password|secret)\b\s*[:=]\s*\S+/', '$1=[REDACTED]', $message) ?? $message;
        $message = preg_replace('/[A-Za-z0-9+\/=]{40,}/', '[REDACTED]', $message) ?? $message;
        $message = preg_replace('/\s+/', ' ', $message) ?? $message;

        return substr(trim($message), 0, 220);
    }
}
