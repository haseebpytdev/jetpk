<?php

namespace App\Services\Suppliers\AirBlue;

use App\Data\TicketingResultData;
use App\Enums\AirBlueApiChannel;
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
        private readonly AirBlueXmlBuilder $xmlBuilder,
        private readonly AirBlueOtaXmlBuilder $otaXmlBuilder,
        private readonly AirBlueResponseNormalizer $normalizer,
        private readonly AirBlueOtaResponseNormalizer $otaNormalizer,
        private readonly AirBlueTicketPreviewService $ticketPreviewService,
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

        if ($this->configResolver->apiChannel($connection) === AirBlueApiChannel::ZapwaysOta) {
            return $this->issueTicketsOta($booking, $connection, $context);
        }

        $orderId = trim((string) ($context['order_id'] ?? $booking->supplier_reference ?? ''));
        $ownerCode = trim((string) ($context['owner_code'] ?? ''));
        if ($orderId === '' || $ownerCode === '') {
            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Airblue->value,
                error_code: 'missing_order_context',
                error_message: 'Ticketing failed, admin review required.',
            );
        }

        try {
            $preview = is_array($context['ticket_preview'] ?? null)
                ? $context['ticket_preview']
                : $this->ticketPreviewService->preview($booking, $connection);

            $config = $this->configResolver->resolve($connection);
            $payment = [
                'amount' => (float) ($preview['amount'] ?? 0),
                'currency' => (string) ($preview['currency'] ?? $config['currency']),
                'ticket_id' => (string) ($context['mco_ticket_id'] ?? '4000012043'),
            ];
            $xml = $this->xmlBuilder->buildTicketingOrderChangeRequest($config, $orderId, $ownerCode, $payment);
            $response = $this->client->call($connection, 'order_change', $xml, [
                'booking_id' => $booking->id,
                'request_context' => 'ticketing',
            ]);
            $normalized = $this->normalizer->normalizeTicketingResponse($response, $context);
            $this->persistTicketing($booking, $normalized);
            $ticketNumbers = is_array($normalized['ticket_numbers'] ?? null) ? $normalized['ticket_numbers'] : [];
            $tickets = array_map(fn (string $num) => ['ticket_number' => $num], $ticketNumbers);

            return new TicketingResultData(
                success: ($normalized['ticketing_status'] ?? '') === 'ticketed',
                status: (string) ($normalized['ticketing_status'] ?? 'failed'),
                provider: SupplierProvider::Airblue->value,
                tickets: $tickets,
                safe_summary: ['order_id' => $orderId],
            );
        } catch (AirBlueException $exception) {
            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Airblue->value,
                error_code: $exception->normalizedCode,
                error_message: $exception->safeMessage,
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
            $config = $this->configResolver->resolveOta($connection);
            $xml = $this->otaXmlBuilder->buildAirDemandTicketRequest($config, $pnr, $instance);
            $response = $this->client->call($connection, 'air_demand_ticket', $xml, [
                'booking_id' => $booking->id,
                'request_context' => 'air_demand_ticket',
            ]);
            $normalized = $this->otaNormalizer->normalizeTicketingResponse($response, $context);
            $this->persistTicketing($booking, $normalized);
            $ticketNumbers = is_array($normalized['ticket_numbers'] ?? null) ? $normalized['ticket_numbers'] : [];
            $tickets = array_map(fn (string $num) => ['ticket_number' => $num], $ticketNumbers);

            return new TicketingResultData(
                success: ($normalized['ticketing_status'] ?? '') === 'ticketed',
                status: (string) ($normalized['ticketing_status'] ?? 'failed'),
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
}
