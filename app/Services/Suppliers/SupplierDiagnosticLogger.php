<?php

namespace App\Services\Suppliers;

use App\Models\SupplierConnection;
use App\Models\SupplierDiagnosticLog;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupplierDiagnosticLogger
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function log(
        SupplierConnection $connection,
        string $action,
        string $status,
        ?int $durationMs = null,
        ?string $safeMessage = null,
        ?string $correlationId = null,
        array $meta = [],
    ): void {
        try {
            SupplierDiagnosticLog::query()->create([
                'agency_id' => $connection->agency_id,
                'supplier_connection_id' => $connection->id,
                'provider' => $connection->provider->value,
                'action' => $action,
                'status' => $status,
                'duration_ms' => $durationMs,
                'safe_message' => SensitiveDataRedactor::sanitizeErrorMessage($safeMessage),
                'correlation_id' => $correlationId,
                'meta' => SensitiveDataRedactor::redact($meta),
            ]);
        } catch (\Throwable $e) {
            Log::warning('supplier_diagnostic_logger_failed', [
                'supplier_connection_id' => $connection->id,
                'action' => $action,
                'exception' => $e::class,
                'message' => Str::limit($e->getMessage(), 120, ''),
            ]);
        }
    }
}
