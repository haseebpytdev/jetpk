<?php

namespace Tests\Unit\Services;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Models\SupplierDiagnosticLog;
use App\Services\Suppliers\SupplierDiagnosticLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupplierDiagnosticLoggerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function log_sanitizes_safe_message_before_persist(): void
    {
        $agency = Agency::factory()->create();
        $connection = SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'name' => 'Test Sabre',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => [
                'client_id' => 'id',
                'client_secret' => 'secret',
            ],
        ]);

        app(SupplierDiagnosticLogger::class)->log(
            connection: $connection,
            action: 'readiness_check',
            status: 'failed',
            safeMessage: 'OAuth failed Bearer eyJsecret.token.value',
        );

        $log = SupplierDiagnosticLog::query()->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Bearer [REDACTED]', (string) $log->safe_message);
        $this->assertStringNotContainsString('eyJsecret', (string) $log->safe_message);
    }
}
