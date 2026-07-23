<?php

namespace Tests\Feature\Console;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\OneApi\OneApiEnablesFixtureTransport;
use Tests\TestCase;

class OneApiTestMatrixCommandTest extends TestCase
{
    use OneApiEnablesFixtureTransport;
    use RefreshDatabase;

    #[Test]
    public function matrix_exports_twenty_four_fixture_rows(): void
    {
        Http::fake(['*' => Http::response(
            json_decode((string) file_get_contents(base_path('tests/Fixtures/Suppliers/OneApi/auth_success.json')), true)
        )]);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'credentials' => [
                'username' => 'ONE_API_TEST_USERNAME',
                'password' => 'ONE_API_TEST_PASSWORD',
                'agent_code' => 'ONE_API_TEST_AGENT',
                'agent_preferred_currency' => 'AED',
                'pos_country' => 'AE',
                'pos_station' => 'DXB',
                'rest_auth_url' => 'https://example.test/auth',
                'rest_search_url' => 'https://example.test/search',
                'soap_url' => 'https://example.test/soap',
            ],
        ]);

        $exit = Artisan::call('ota:one-api-test-matrix', [
            '--mode' => 'fixture',
            '--connection' => $connection->id,
            '--output' => storage_path('app/one-api-matrix-test'),
        ]);

        $this->assertSame(0, $exit);
        $files = glob(storage_path('app/one-api-matrix-test/one-api-matrix-*.csv')) ?: [];
        $this->assertNotEmpty($files);
        $csv = $files[0];
        $lines = file($csv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertNotFalse($lines);
        $this->assertCount(25, $lines);
        $this->assertStringContainsString('Failures: 0', Artisan::output());
    }

    #[Test]
    public function matrix_case_filter_runs_single_row(): void
    {
        Http::fake(['*' => Http::response(
            json_decode((string) file_get_contents(base_path('tests/Fixtures/Suppliers/OneApi/auth_success.json')), true)
        )]);
        $connection = $this->oneApiConnection();
        $exit = Artisan::call('ota:one-api-test-matrix', [
            '--mode' => 'fixture',
            '--connection' => $connection->id,
            '--case' => 'oneway_basic_1',
            '--output' => storage_path('app/one-api-matrix-case-test'),
        ]);
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Matrix cases: 1', Artisan::output());
    }

    #[Test]
    public function invalid_matrix_case_returns_non_zero(): void
    {
        $connection = $this->oneApiConnection();
        $exit = Artisan::call('ota:one-api-test-matrix', [
            '--mode' => 'fixture',
            '--connection' => $connection->id,
            '--case' => 'not_a_real_case',
            '--output' => storage_path('app/one-api-matrix-invalid'),
        ]);
        $this->assertSame(1, $exit);
    }

    #[Test]
    public function dry_run_does_not_create_success_booking_row(): void
    {
        Http::fake(['*' => Http::response(
            json_decode((string) file_get_contents(base_path('tests/Fixtures/Suppliers/OneApi/auth_success.json')), true)
        )]);
        $connection = $this->oneApiConnection();
        $exit = Artisan::call('ota:one-api-test-matrix', [
            '--mode' => 'fixture',
            '--connection' => $connection->id,
            '--case' => 'oneway_basic_1',
            '--dry-run' => true,
            '--output' => storage_path('app/one-api-matrix-dry'),
        ]);
        $this->assertSame(0, $exit);
    }

    private function oneApiConnection(): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'credentials' => [
                'username' => 'ONE_API_TEST_USERNAME',
                'password' => 'ONE_API_TEST_PASSWORD',
                'agent_code' => 'ONE_API_TEST_AGENT',
                'agent_preferred_currency' => 'AED',
                'pos_country' => 'AE',
                'pos_station' => 'DXB',
                'rest_auth_url' => 'https://example.test/auth',
                'rest_search_url' => 'https://example.test/search',
                'soap_url' => 'https://example.test/soap',
            ],
        ]);
    }
}
