<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use App\Services\Suppliers\PiaNdc\PiaNdcCancelEvidenceService;
use Illuminate\Console\Command;

class PiaNdcCancelEvidenceReportCommand extends Command
{
    protected $signature = 'pia-ndc:cancel-evidence-report
        {--connection= : Supplier connection ID}
        {--order-id= : Hitit OrderID / PNR}
        {--pnr= : Alias for order-id}
        {--owner-code=PK : OwnerCode}
        {--output= : Output directory (default: storage/app/diagnostics/pia-ndc/cancel-evidence/...)}';

    protected $description = 'Build sanitized Hitit cancel evidence package for support (no supplier calls)';

    public function handle(PiaNdcCancelEvidenceService $evidenceService): int
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

        $output = trim((string) $this->option('output'));

        try {
            $result = $evidenceService->buildReport(
                connection: $connection,
                orderId: $orderId,
                ownerCode: $ownerCode,
                outputPath: $output !== '' ? $output : null,
            );
        } catch (PiaNdcValidationException $exception) {
            $this->error($exception->safeMessage);

            return self::FAILURE;
        }

        $summary = $result['summary'];
        $this->line('success=true');
        $this->line('supplier_called=false');
        $this->line('order_id='.($summary['order_id'] ?? ''));
        $this->line('retrieve_count='.($summary['retrieve_count'] ?? 0));
        $this->line('cancel_count='.($summary['cancel_count'] ?? 0));
        $this->line('output_path='.($result['output_path'] ?? ''));
        $this->line('support_message_path='.($summary['support_message_path'] ?? ''));

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveOrderReference(): array
    {
        $orderId = trim((string) ($this->option('order-id')
            ?: $this->option('pnr')));
        $ownerCode = trim((string) $this->option('owner-code')) ?: 'PK';

        if ($orderId === '') {
            throw new PiaNdcValidationException(
                'missing_order_reference',
                422,
                'Provide --order-id or --pnr.',
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
