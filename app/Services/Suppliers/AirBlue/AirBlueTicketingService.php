<?php

namespace App\Services\Suppliers\AirBlue;

use App\Data\TicketingResultData;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueTicketingException;
use Illuminate\Support\Facades\Log;

class AirBlueTicketingService
{
    public function __construct(
        private readonly AirBlueClient $client,
        private readonly AirBlueConfigResolver $configResolver,
        private readonly AirBlueOtaXmlBuilder $otaXmlBuilder,
        private readonly AirBlueOtaResponseNormalizer $otaNormalizer,
        private readonly AirBlueProtocolGuard $protocolGuard,
        private readonly AirBlueSeatSelectionGate $seatSelectionGate,
    ) {}

    public function issueTickets(Booking $booking, SupplierConnection $connection, User $actor): TicketingResultData
    {
        unset($actor);

        if ($this->isAlreadyTicketed($booking)) {
            return new TicketingResultData(
                success: false,
                status: 'duplicate_ticketing_guard',
                provider: SupplierProvider::Airblue->value,
                error_code: 'duplicate_ticketing_guard',
                error_message: 'Tickets have already been issued for this booking.',
            );
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];

        return $this->issueTicketsOta($booking, $connection, $context);
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function persistTicketing(Booking $booking, array $normalized): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = array_merge(
            is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [],
            is_array($normalized['provider_context'] ?? null) ? $normalized['provider_context'] : [],
        );
        $meta['airblue_context'] = $context;
        $booking->meta = $meta;
        $booking->save();

        SupplierBooking::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Airblue->value)
            ->update(['status' => ($normalized['ticketing_status'] ?? '') === 'ticketed' ? 'ticketed' : 'confirmed']);
    }

    private function isAlreadyTicketed(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];

        return ($context['ticketing_status'] ?? '') === 'ticketed'
            || (is_array($context['ticket_numbers'] ?? null) && $context['ticket_numbers'] !== []);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function issueTicketsOta(Booking $booking, SupplierConnection $connection, array $context): TicketingResultData
    {
        if (! $this->isTicketingFeatureEnabled($connection)) {
            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Airblue->value,
                error_code: 'ticketing_feature_disabled',
                error_message: 'AirBlue ticketing is not enabled for this connection.',
            );
        }

        $pnr = trim((string) ($context['pnr'] ?? $booking->pnr ?? $booking->supplier_reference ?? ''));
        $instance = trim((string) ($context['instance'] ?? ''));
        if ($pnr === '' || $instance === '') {
            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Airblue->value,
                error_code: 'missing_order_context',
                error_message: 'Ticketing failed, admin review required.',
            );
        }

        try {
            $this->protocolGuard->assertCompatible($connection, $context, 'ticketing');
            $config = $this->configResolver->resolveOta($connection);
            $this->seatSelectionGate->assertSeatSelectionSatisfied($config, $context, [
                'seats' => is_array($context['seats'] ?? null) ? $context['seats'] : [],
            ]);
            $paymentContext = $this->resolvePaymentContext($context, $config);
            $xml = $this->otaXmlBuilder->buildAirDemandTicketRequest($config, $pnr, $instance, $paymentContext);
            $response = $this->client->callOta($connection, 'air_demand_ticket', $xml, [
                'booking_id' => $booking->id,
                'request_context' => 'air_demand_ticket',
            ]);
            $normalized = $this->otaNormalizer->normalizeTicketingResponse($response, $context);
            $this->persistTicketing($booking, $normalized);
            $ticketNumbers = is_array($normalized['ticket_numbers'] ?? null) ? $normalized['ticket_numbers'] : [];
            $tickets = array_map(fn (string $num) => ['ticket_number' => $num], $ticketNumbers);

            $status = (string) ($normalized['ticketing_status'] ?? 'failed');

            return new TicketingResultData(
                success: in_array($status, ['ticketed', 'paid'], true),
                status: $status,
                provider: SupplierProvider::Airblue->value,
                tickets: $tickets,
                safe_summary: ['pnr' => $pnr],
            );
        } catch (AirBlueException $exception) {
            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Airblue->value,
                error_code: $exception->normalizedCode,
                error_message: $exception->safeMessage,
                safe_summary: $this->safeProviderErrorSummary($exception),
            );
        } catch (\Throwable $exception) {
            Log::channel('air-blue')->warning('airblue.ticketing.unexpected', [
                'booking_id' => $booking->id,
                'exception' => $exception::class,
            ]);

            throw new AirBlueTicketingException(
                'ticketing_unexpected',
                500,
                'Ticketing failed, admin review required.',
                ['booking_id' => $booking->id],
                $exception,
            );
        }
    }

    /**
     * Product ticketing requires an explicit connection feature flag (default false).
     */
    private function isTicketingFeatureEnabled(SupplierConnection $connection): bool
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];

        return filter_var($credentials['ticketing_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function resolvePaymentContext(array $context, array $config): array
    {
        $amount = $context['supplier_payable_amount'] ?? $context['payment_amount'] ?? null;
        if ($amount === null || $amount === '') {
            throw new AirBlueTicketingException(
                'missing_payment_amount',
                422,
                'Ticketing failed, admin review required.',
                ['reason' => 'supplier_payable_amount_required'],
            );
        }

        return [
            'amount' => $amount,
            'currency' => $context['payment_currency'] ?? $config['currency'] ?? 'PKR',
            'payment_type' => $context['payment_type'] ?? 'Cash',
            'ticketing_mode' => $context['ticketing_mode'] ?? 'ticket',
        ];
    }

    /**
     * Sanitized provider diagnostics for ops — never credentials, full XML, PNR, tickets, or PII.
     *
     * @return array<string, mixed>
     */
    private function safeProviderErrorSummary(AirBlueException $exception): array
    {
        $summary = [];
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
            $message = $this->sanitizeProviderErrorMessage((string) ($error['message'] ?? ''));
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

    private function sanitizeProviderErrorMessage(string $message): string
    {
        $message = html_entity_decode($message, ENT_QUOTES | ENT_XML1);
        $message = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[REDACTED_EMAIL]', $message) ?? $message;
        $message = preg_replace('/(?i)\b(agentpassword|messagepassword|client[_-]?key|client[_-]?id|password|secret)\b\s*[:=]\s*\S+/', '$1=[REDACTED]', $message) ?? $message;
        $message = preg_replace('/[A-Za-z0-9+\/=]{40,}/', '[REDACTED]', $message) ?? $message;
        $message = preg_replace('/<(?:[\w]+:)?(?:Email|GivenName|Surname|DocID|MessagePassword)[^>]*>.*?<\/(?:[\w]+:)?(?:Email|GivenName|Surname|DocID|MessagePassword)>/is', '[REDACTED]', $message) ?? $message;
        $message = preg_replace('/\s+/', ' ', $message) ?? $message;

        return substr(trim($message), 0, 220);
    }
}
