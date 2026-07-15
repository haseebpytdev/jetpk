<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use App\Services\Suppliers\PiaNdc\PiaNdcBookingStatusRefreshService;
use App\Services\Suppliers\PiaNdc\PiaNdcReleaseOptionPnrService;
use App\Support\Bookings\PiaNdcOperationAuditRecorder;
use Illuminate\Console\Command;

class PiaNdcReleaseOptionPnrCommand extends Command
{
    protected $signature = 'pia-ndc:release-option-pnr
        {--connection= : Supplier connection ID}
        {--order-id= : Hitit OrderID}
        {--pnr= : Alias for order-id}
        {--booking-reference= : Local booking reference (e.g. AMGE23KH)}
        {--owner-code=PK : OwnerCode}
        {--reason= : Operator reason (required for execute)}
        {--clear-stale-locks : Remove expired release lock files only}
        {--execute-release : Call DoOrderCancelCommit after preview}
        {--confirm= : Required confirmation phrase when executing}';

    protected $description = 'Controlled release for unticketed PIA NDC option PNRs (retrieve + preview; commit only with explicit confirmation)';

    private ?Booking $resolvedBooking = null;

    public function handle(
        PiaNdcReleaseOptionPnrService $releaseService,
        PiaNdcBookingStatusRefreshService $statusRefreshService,
        PiaNdcOperationAuditRecorder $operationAuditRecorder,
    ): int {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('No PIA NDC SupplierConnection found.');

            return self::FAILURE;
        }

        $clearStaleLocks = (bool) $this->option('clear-stale-locks');
        if ($clearStaleLocks) {
            $removed = $releaseService->clearStaleReleaseLocks($connection->id);
            $this->line('stale_release_locks_removed='.count($removed));
            if (! $this->option('execute-release') && trim((string) $this->option('order-id')) === '' && trim((string) $this->option('pnr')) === '' && trim((string) $this->option('booking-reference')) === '') {
                return self::SUCCESS;
            }
        }

        try {
            [$orderId, $ownerCode] = $this->resolveOrderReference();
        } catch (PiaNdcValidationException $exception) {
            $this->error($exception->safeMessage);

            return self::FAILURE;
        }

        $executeRelease = (bool) $this->option('execute-release');
        $confirmPhrase = trim((string) $this->option('confirm'));
        $reason = trim((string) $this->option('reason'));

        try {
            $result = $releaseService->runReleaseDiagnostic(
                connection: $connection,
                orderId: $orderId,
                ownerCode: $ownerCode,
                executeRelease: $executeRelease,
                confirmPhrase: $confirmPhrase !== '' ? $confirmPhrase : null,
                reason: $reason !== '' ? $reason : null,
                booking: $this->resolvedBooking,
            );
        } catch (PiaNdcValidationException $exception) {
            $this->error($exception->safeMessage);

            return self::FAILURE;
        }

        $summary = $result['summary'];
        $this->line('connection_id='.($summary['connection_id'] ?? ''));
        $this->line('correlation_id='.($summary['correlation_id'] ?? ''));
        $this->line('order_id='.($summary['order_id'] ?? ''));
        $this->line('owner_code='.($summary['owner_code'] ?? ''));
        $this->line('dry_run='.(($summary['dry_run'] ?? true) ? 'true' : 'false'));
        $this->line('retrieve_http_status='.($summary['retrieve_http_status'] ?? ''));
        $this->line('retrieve_success='.(($summary['retrieve_success'] ?? false) ? 'true' : 'false'));
        $this->line('preview_http_status='.($summary['preview_http_status'] ?? ''));
        $this->line('preview_success='.(($summary['preview_success'] ?? false) ? 'true' : 'false'));
        $this->line('commit_http_status='.($summary['commit_http_status'] ?? ''));
        $this->line('commit_success='.($summary['commit_success'] === null ? '' : (($summary['commit_success'] ?? false) ? 'true' : 'false')));
        $this->line('final_retrieve_http_status='.($summary['final_retrieve_http_status'] ?? ''));
        $this->line('final_retrieve_success='.($summary['final_retrieve_success'] === null ? '' : (($summary['final_retrieve_success'] ?? false) ? 'true' : 'false')));
        $this->line('cancellation_status='.($summary['cancellation_status'] ?? ''));
        $this->line('order_status='.($summary['order_status'] ?? ''));
        $ticketNumbers = $summary['ticket_numbers'] ?? [];
        $this->line('ticket_numbers='.(is_array($ticketNumbers) ? implode(',', $ticketNumbers) : ''));
        $this->line('has_blocking_ticket_numbers='.(($summary['has_blocking_ticket_numbers'] ?? false) ? 'true' : 'false'));
        $this->line('diagnostic_path='.($summary['diagnostic_path'] ?? ''));

        if ($executeRelease) {
            $booking = $this->resolveBookingForCliAudit($summary);
            if ($booking !== null) {
                $operationAuditRecorder->recordReleaseOptionPnr(
                    booking: $booking,
                    connection: $connection,
                    actor: null,
                    summary: array_merge($summary, ['success' => (bool) ($result['success'] ?? false)]),
                    operatorReason: $reason !== '' ? $reason : null,
                );
            }

            if (($result['success'] ?? false) === true) {
                $this->reconcileLocalBookingAfterExecute($statusRefreshService, $connection, $summary);
            }
        }

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function reconcileLocalBookingAfterExecute(
        PiaNdcBookingStatusRefreshService $statusRefreshService,
        SupplierConnection $connection,
        array $summary,
    ): void {
        $booking = $this->resolvedBooking;
        if ($booking === null) {
            $orderId = trim((string) ($summary['order_id'] ?? ''));
            if ($orderId === '') {
                return;
            }

            $matches = Booking::query()
                ->where('supplier', SupplierProvider::PiaNdc->value)
                ->where(function ($query) use ($orderId): void {
                    $query->where('pnr', $orderId)
                        ->orWhere('supplier_reference', $orderId);
                })
                ->get();

            if ($matches->count() === 1) {
                $booking = $matches->first();
            } elseif ($matches->count() > 1) {
                $this->warn('Multiple local bookings share PNR/order '.$orderId.'; pass --booking-reference to update local state.');

                return;
            }
        }

        if ($booking === null) {
            return;
        }

        $statusRefreshService->reconcileLocalAfterSuccessfulRelease(
            booking: $booking,
            connection: $connection,
            releaseSummary: $summary,
        );
        $this->line('local_booking_reconciled='.$booking->booking_reference);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function resolveBookingForCliAudit(array $summary): ?Booking
    {
        $booking = $this->resolvedBooking;
        if ($booking !== null) {
            return $booking;
        }

        $orderId = trim((string) ($summary['order_id'] ?? ''));
        if ($orderId === '') {
            return null;
        }

        $matches = Booking::query()
            ->where('supplier', SupplierProvider::PiaNdc->value)
            ->where(function ($query) use ($orderId): void {
                $query->where('pnr', $orderId)
                    ->orWhere('supplier_reference', $orderId);
            })
            ->get();

        if ($matches->count() === 1) {
            return $matches->first();
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveOrderReference(): array
    {
        $bookingReference = trim((string) $this->option('booking-reference'));
        if ($bookingReference !== '') {
            $booking = Booking::query()
                ->where('booking_reference', $bookingReference)
                ->where('supplier', SupplierProvider::PiaNdc->value)
                ->first();
            if ($booking === null) {
                throw new PiaNdcValidationException(
                    'booking_not_found',
                    422,
                    'No PIA NDC booking found for booking reference '.$bookingReference.'.',
                );
            }

            $this->resolvedBooking = $booking;
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
            $orderId = trim((string) ($context['order_id'] ?? $booking->supplier_reference ?? $booking->pnr ?? ''));
            $ownerCode = trim((string) ($context['owner_code'] ?? (string) $this->option('owner-code'))) ?: 'PK';
            if ($orderId === '') {
                throw new PiaNdcValidationException(
                    'missing_order_reference',
                    422,
                    'PIA NDC order reference is missing on booking '.$bookingReference.'.',
                );
            }

            return [$orderId, $ownerCode];
        }

        $orderId = trim((string) ($this->option('order-id') ?: $this->option('pnr')));
        $ownerCode = trim((string) $this->option('owner-code')) ?: 'PK';

        if ($orderId === '') {
            throw new PiaNdcValidationException(
                'missing_order_reference',
                422,
                'Provide --order-id, --pnr, or --booking-reference.',
            );
        }

        return [$orderId, $ownerCode];
    }

    protected function resolveConnection(): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id) {
            return SupplierConnection::query()->where('id', (int) $id)->where('provider', SupplierProvider::PiaNdc)->first();
        }

        return SupplierConnection::query()->where('provider', SupplierProvider::PiaNdc)->orderByDesc('is_active')->first();
    }
}
