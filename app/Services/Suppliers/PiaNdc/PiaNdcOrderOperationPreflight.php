<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcCancellationException;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcTicketingException;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use App\Support\Bookings\PiaNdcVoidLocalReconciliation;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Shared OrderRetrieve preflight and guard checks for PIA NDC ticketing / void CLI paths.
 */
class PiaNdcOrderOperationPreflight
{
    public function __construct(
        private readonly PiaNdcRetrieveService $retrieveService,
        private readonly PiaNdcResponseNormalizer $normalizer,
        private readonly PiaNdcBookingStatusRefreshService $statusRefreshService,
    ) {}

    /**
     * @return array{order_id: string, owner_code: string, context: array<string, mixed>}
     */
    public function orderContext(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $orderId = trim((string) ($context['order_id'] ?? $booking->supplier_reference ?? ''));
        $ownerCode = trim((string) ($context['owner_code'] ?? ''));

        return [
            'order_id' => $orderId,
            'owner_code' => $ownerCode,
            'context' => $context,
        ];
    }

    /**
     * @throws PiaNdcTicketingException|PiaNdcCancellationException
     */
    public function assertOrderContext(Booking $booking, string $operation = 'ticketing'): array
    {
        $resolved = $this->orderContext($booking);
        if ($resolved['order_id'] === '' || $resolved['owner_code'] === '') {
            $message = $operation === 'void'
                ? 'Void ticket failed.'
                : 'Ticketing failed, admin review required.';

            $exceptionClass = $operation === 'void'
                ? PiaNdcCancellationException::class
                : PiaNdcTicketingException::class;

            throw new $exceptionClass('missing_order_context', 422, $message);
        }

        return $resolved;
    }

    /**
     * @return array{synced: bool, reason?: string, data?: array<string, mixed>}
     */
    public function freshRetrieve(Booking $booking, SupplierConnection $connection, ?User $actor = null, string $source = 'operation_preflight'): array
    {
        $booking->refresh();

        try {
            $result = $this->statusRefreshService->refreshBooking($booking, $actor, $source);

            return [
                'synced' => true,
                'data' => array_merge(
                    is_array($result['summary'] ?? null) ? $result['summary'] : [],
                    is_array($result['interpreted'] ?? null) ? $result['interpreted'] : [],
                ),
            ];
        } catch (PiaNdcValidationException $exception) {
            return [
                'synced' => false,
                'reason' => $exception->normalizedCode,
            ];
        }
    }

    /**
     * @param  array{synced: bool, reason?: string, data?: array<string, mixed>}  $retrieveResult
     *
     * @throws PiaNdcTicketingException|PiaNdcCancellationException
     */
    public function assertRetrieveSucceeded(array $retrieveResult, string $operation = 'ticketing'): array
    {
        if (($retrieveResult['synced'] ?? false) !== true) {
            $exceptionClass = $operation === 'void'
                ? PiaNdcCancellationException::class
                : PiaNdcTicketingException::class;

            throw new $exceptionClass(
                'preflight_retrieve_failed',
                422,
                $operation === 'void'
                    ? 'Void refused: preflight retrieve did not succeed.'
                    : 'Ticketing refused: preflight retrieve did not succeed.',
                ['reason' => (string) ($retrieveResult['reason'] ?? 'unknown')],
            );
        }

        return is_array($retrieveResult['data'] ?? null) ? $retrieveResult['data'] : [];
    }

    public function paymentTimeLimitExpired(?string $paymentTimeLimit): bool
    {
        $limit = trim((string) $paymentTimeLimit);
        if ($limit === '') {
            return false;
        }

        try {
            return Carbon::parse($limit)->isPast();
        } catch (\Throwable) {
            return false;
        }
    }

    public function duplicateTicketGuard(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];

        if (($context['ticketing_status'] ?? '') === 'ticketed') {
            return true;
        }

        $ticketNumbers = is_array($context['ticket_numbers'] ?? null) ? $context['ticket_numbers'] : [];
        $ticketDocInfos = is_array($context['ticket_doc_infos'] ?? null) ? $context['ticket_doc_infos'] : [];

        if ($ticketDocInfos !== []) {
            return $this->normalizer->hasBlockingTicketNumbers($ticketDocInfos);
        }

        return $ticketNumbers !== [] && $this->normalizer->hasBlockingTicketNumbers(
            array_map(fn (string $num) => ['ticket_number' => $num], $ticketNumbers),
        );
    }

    public function duplicateVoidGuard(Booking $booking): bool
    {
        if (PiaNdcVoidLocalReconciliation::isVoided($booking)) {
            return true;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];

        return ($context['void_status'] ?? '') === 'voided';
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function realTicketNumbersPresent(array $summary): bool
    {
        if (($summary['has_blocking_ticket_numbers'] ?? false) === true) {
            return true;
        }

        $ticketDocInfos = is_array($summary['ticket_doc_infos'] ?? null) ? $summary['ticket_doc_infos'] : [];
        if ($ticketDocInfos !== []) {
            return $this->normalizer->hasBlockingTicketNumbers($ticketDocInfos);
        }

        $ticketNumbers = is_array($summary['ticket_numbers'] ?? null) ? $summary['ticket_numbers'] : [];
        if ($ticketNumbers === []) {
            return false;
        }

        return $this->normalizer->hasBlockingTicketNumbers(
            array_map(fn (mixed $num) => ['ticket_number' => (string) $num], $ticketNumbers),
        );
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function onlyPlaceholderTicketNumbers(array $summary): bool
    {
        $ticketNumbers = is_array($summary['ticket_numbers'] ?? null) ? $summary['ticket_numbers'] : [];
        if ($ticketNumbers === []) {
            return false;
        }

        return ! $this->realTicketNumbersPresent($summary);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function saveOperationDiagnostic(
        int $connectionId,
        string $operation,
        string $correlationId,
        array $summary,
        ?string $requestXml = null,
    ): string {
        $directory = storage_path('app/diagnostics/pia-ndc/'.$operation.'/'.$connectionId.'/'.$correlationId);
        File::ensureDirectoryExists($directory);
        file_put_contents(
            $directory.'/summary.json',
            json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
        if ($requestXml !== null && $requestXml !== '') {
            file_put_contents($directory.'/request.xml', $requestXml);
        }

        return $directory;
    }
}
