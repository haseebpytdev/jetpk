<?php

namespace Tests\Feature\Suppliers;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Support\OneApi\OneApiFixtureTransportScope;
use App\Support\OneApi\OneApiMatrixCaseRegistry;
use App\Support\OneApi\OneApiTestMatrixRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\OneApi\OneApiEnablesFixtureTransport;
use Tests\TestCase;

class OneApiMatrixTwentyFourCasesTest extends TestCase
{
    use OneApiEnablesFixtureTransport;
    use RefreshDatabase;

    public static function matrixCaseProvider(): array
    {
        $rows = [];
        foreach (OneApiMatrixCaseRegistry::cases() as $case) {
            $rows[$case['key']] = [$case];
        }

        return $rows;
    }

    #[DataProvider('matrixCaseProvider')]
    public function test_each_workbook_case_passes_fixture_lifecycle(array $case): void
    {
        OneApiFixtureTransportScope::enable('matrix_command');
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

        $row = app(OneApiTestMatrixRunner::class)->runCase($connection, $case, false);

        $this->assertSame($case['key'], $row['internal_case_key'] ?? '');
        $this->assertSame('pass', $row['result'] ?? '');
        $this->assertNotEmpty($row['PNR'] ?? '');
        $this->assertNotEmpty($row['TRANSACTION IDENTIFIER'] ?? '');
        $this->assertNotSame('', trim((string) ($row['PNR'] ?? '')));
    }

    public function test_registry_contains_twenty_four_unique_case_ids(): void
    {
        $cases = OneApiMatrixCaseRegistry::cases();
        $this->assertCount(24, $cases);
        $keys = array_column($cases, 'key');
        $this->assertCount(24, array_unique($keys));
    }
}
