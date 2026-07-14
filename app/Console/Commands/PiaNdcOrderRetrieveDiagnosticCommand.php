<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use App\Services\Suppliers\PiaNdc\PiaNdcRetrieveService;
use Illuminate\Console\Command;

class PiaNdcOrderRetrieveDiagnosticCommand extends Command
{
    protected $signature = 'pia-ndc:order-retrieve-diagnostic
        {--connection= : Supplier connection ID}
        {--order-id= : Hitit OrderID}
        {--pnr= : Alias for order-id}
        {--booking-reference= : Alias for order-id}
        {--owner-code=PK : OwnerCode}';

    protected $description = 'CLI DoOrderRetrieve diagnostic for PIA NDC Hitit Crane 20.1 option PNR validation';

    public function handle(PiaNdcRetrieveService $retrieveService): int
    {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('No PIA NDC SupplierConnection found.');

            return self::FAILURE;
        }

        try {
            [$orderId, $ownerCode] = $this->resolveOrderReference();
        } catch (PiaNdcValidationException $exception) {
            $this->error($exception->safeMessage);

            return self::FAILURE;
        }

        try {
            $result = $retrieveService->runOrderRetrieveDiagnostic($connection, $orderId, $ownerCode);
        } catch (PiaNdcValidationException $exception) {
            $this->error($exception->safeMessage);

            return self::FAILURE;
        }

        $summary = $result['summary'];
        $this->line('connection_id='.($summary['connection_id'] ?? ''));
        $this->line('correlation_id='.($summary['correlation_id'] ?? ''));
        $this->line('order_id='.($summary['order_id'] ?? ''));
        $this->line('pnr='.($summary['pnr'] ?? ''));
        $this->line('booking_reference='.($summary['booking_reference'] ?? ''));
        $this->line('airline_locator='.($summary['airline_locator'] ?? ''));
        $this->line('order_status='.($summary['order_status'] ?? ''));
        $this->line('payment_time_limit='.($summary['payment_time_limit'] ?? ''));
        $this->line('segment_count='.($summary['segment_count'] ?? ''));
        $this->line('passenger_count='.($summary['passenger_count'] ?? ''));
        $ticketNumbers = $summary['ticket_numbers'] ?? [];
        $this->line('ticket_numbers='.(is_array($ticketNumbers) ? implode(',', $ticketNumbers) : ''));
        $this->line('has_blocking_ticket_numbers='.(($summary['has_blocking_ticket_numbers'] ?? false) ? 'true' : 'false'));
        $this->line('http_status='.($summary['http_status'] ?? ''));
        $this->line('success='.(($summary['success'] ?? false) ? 'true' : 'false'));
        $this->line('provider_error_code='.($summary['provider_error_code'] ?? ''));
        $this->line('provider_error_message='.($summary['provider_error_message'] ?? ''));
        $this->line('diagnostic_path='.($summary['diagnostic_path'] ?? ''));

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveOrderReference(): array
    {
        $orderId = trim((string) ($this->option('order-id')
            ?: $this->option('pnr')
            ?: $this->option('booking-reference')));
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
