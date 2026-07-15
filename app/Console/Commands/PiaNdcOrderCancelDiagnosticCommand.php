<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use App\Services\Suppliers\PiaNdc\PiaNdcCancelService;
use Illuminate\Console\Command;

class PiaNdcOrderCancelDiagnosticCommand extends Command
{
    protected $signature = 'pia-ndc:order-cancel-diagnostic
        {--connection= : Supplier connection ID}
        {--order-id= : Hitit OrderID}
        {--pnr= : Alias for order-id}
        {--booking-reference= : Alias for order-id}
        {--owner-code=PK : OwnerCode}
        {--shape= : Cancel request shape (required for execute)}
        {--operation= : SOAP operation override (doOrderCancel|doOrderChange|doOrderCancelCommit|doOrderCancelPreview)}
        {--probe-shapes : Build all cancel shape variants without supplier calls}
        {--clear-stale-locks : Remove expired cancel preview/commit lock files only}
        {--execute-cancel : Call supplier cancel operation (releases option PNR)}
        {--confirm= : Required confirmation phrase when executing}';

    protected $description = 'CLI OrderCancel diagnostic for PIA NDC unticketed option PNR (dry-run by default)';

    public function handle(PiaNdcCancelService $cancelService): int
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

        $executeCancel = (bool) $this->option('execute-cancel');
        $probeShapes = (bool) $this->option('probe-shapes');
        $clearStaleLocks = (bool) $this->option('clear-stale-locks');
        $confirmPhrase = trim((string) $this->option('confirm'));
        $shape = trim((string) $this->option('shape'));
        $operation = trim((string) $this->option('operation'));

        if ($clearStaleLocks) {
            $removed = $cancelService->clearStaleCancelLocks(
                $connection->id,
            );
            $this->line('stale_locks_removed='.count($removed));
            if (! $executeCancel && ! $probeShapes && $shape === '' && $operation === '') {
                return self::SUCCESS;
            }
        }

        if ($executeCancel && $confirmPhrase === '') {
            $this->error('Execute preview requires --confirm="PREVIEW_OPTION_PNR" (--shape=hitit_cancel_preview_sample_exact|hitit_cancel_preview_sample_exact_with_contact_info|hitit_cancel_preview_sample_exact_with_orderref_owner_attr).');

            return self::FAILURE;
        }

        try {
            $result = $cancelService->runOrderCancelDiagnostic(
                connection: $connection,
                orderId: $orderId,
                ownerCode: $ownerCode,
                executeCancel: $executeCancel,
                confirmPhrase: $confirmPhrase !== '' ? $confirmPhrase : null,
                probeShapes: $probeShapes,
                shape: $shape !== '' ? $shape : null,
                operationOverride: $operation !== '' ? $operation : null,
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
        if (isset($summary['shape'])) {
            $this->line('shape='.($summary['shape'] ?? ''));
        }
        if (isset($summary['operation'])) {
            $this->line('operation='.($summary['operation'] ?? ''));
        }
        $this->line('dry_run='.(($summary['dry_run'] ?? true) ? 'true' : 'false'));
        $this->line('supplier_called='.(($summary['supplier_called'] ?? false) ? 'true' : 'false'));
        if (array_key_exists('probe_shapes', $summary)) {
            $this->line('probe_shapes='.(($summary['probe_shapes'] ?? false) ? 'true' : 'false'));
            $this->line('variant_count='.($summary['variant_count'] ?? 0));
        }
        $this->line('http_status='.($summary['http_status'] ?? ''));
        $this->line('success='.(($summary['success'] ?? false) ? 'true' : 'false'));
        $this->line('provider_error_code='.($summary['provider_error_code'] ?? ''));
        $this->line('provider_error_message='.($summary['provider_error_message'] ?? ''));
        $this->line('soap_fault_code='.($summary['soap_fault_code'] ?? ''));
        $this->line('soap_fault_string='.($summary['soap_fault_string'] ?? ''));
        if (array_key_exists('cancel_preview_status', $summary)) {
            $this->line('cancel_preview_status='.($summary['cancel_preview_status'] ?? ''));
        }
        if (array_key_exists('cancellation_status', $summary)) {
            $this->line('cancellation_status='.($summary['cancellation_status'] ?? ''));
        }
        $this->line('order_status='.($summary['order_status'] ?? ''));
        $this->line('diagnostic_path='.($summary['diagnostic_path'] ?? ''));

        if ($this->getOutput()->isVerbose() && is_string($summary['diagnostic_path'] ?? null)) {
            $this->outputRequestShapeGrep($summary['diagnostic_path']);
        }

        if (isset($result['probe_results']) && is_array($result['probe_results'])) {
            foreach ($result['probe_results'] as $probeResult) {
                if (! is_array($probeResult)) {
                    continue;
                }
                $this->line('probe_variant shape='.($probeResult['shape'] ?? '').' operation='.($probeResult['operation'] ?? '').' path='.($probeResult['diagnostic_path'] ?? ''));
            }
        }

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    private function outputRequestShapeGrep(string $diagnosticPath): void
    {
        $requestPath = rtrim($diagnosticPath, '/\\').'/request.xml';
        if (! is_file($requestPath)) {
            return;
        }

        $xml = file_get_contents($requestPath) ?: '';
        $needles = [
            'Party',
            'TravelAgency',
            'AgencyID',
            'ContactInfo',
            'EmailAddress',
            'EmailAddressText',
            'OrderRefID',
            'RequestedCurCode',
            'DeviceOwnerTypeCode',
            'LangCode',
            'UpdateOrder',
            'CancelOrder',
            'PayloadAttributes',
            'PrimaryLangID',
            'ChangeOrder',
            'OrderID',
            'OwnerCode',
            'OrderChangeParameters',
            'CurCode',
        ];

        $this->line('verbose_request_shape_grep:');
        foreach ($needles as $needle) {
            if (str_contains($xml, '<'.$needle.'>') || str_contains($xml, '<'.$needle.' ')) {
                $this->line('  found='.$needle);
            }
        }
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
