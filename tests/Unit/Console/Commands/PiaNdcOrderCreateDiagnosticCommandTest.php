<?php

namespace Tests\Unit\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PiaNdcOrderCreateDiagnosticCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_builds_request_without_calling_supplier(): void
    {
        $connection = $this->piaConnection();
        $directory = $this->seedDiagnosticInput();

        Http::fake();

        $this->artisan('pia-ndc:order-create-diagnostic', array_merge(
            $this->validPassengerOptions(),
            [
                '--connection' => $connection->id,
                '--diagnostic-path' => $directory,
                '--offer-index' => 0,
            ],
        ))
            ->expectsOutputToContain('dry_run=true')
            ->expectsOutputToContain('supplier_called=false')
            ->expectsOutputToContain('success=true')
            ->assertSuccessful();

        Http::assertNothingSent();
        $diagRoot = storage_path('app/diagnostics/pia-ndc/order-create/'.$connection->id);
        $this->assertDirectoryExists($diagRoot);
        File::deleteDirectory($directory);
        File::deleteDirectory($diagRoot);
    }

    public function test_dry_run_request_xml_contains_required_offer_and_passenger_nodes(): void
    {
        $connection = $this->piaConnection();
        $directory = $this->seedDiagnosticInput();

        Http::fake();

        $this->artisan('pia-ndc:order-create-diagnostic', array_merge(
            $this->validPassengerOptions(),
            [
                '--connection' => $connection->id,
                '--diagnostic-path' => $directory,
            ],
        ))->assertSuccessful();

        $diagRoot = storage_path('app/diagnostics/pia-ndc/order-create/'.$connection->id);
        $folders = File::directories($diagRoot);
        $this->assertNotEmpty($folders);
        $requestXml = file_get_contents($folders[0].'/request.xml') ?: '';
        $this->assertStringContainsString('raw-hitit-offer-id-for-order-create', $requestXml);
        $this->assertStringContainsString('OfferItem-13', $requestXml);
        $this->assertStringContainsString('ADTPax-1', $requestXml);
        $this->assertStringContainsString('OwnerCode', $requestXml);
        $this->assertStringContainsString('AB1234567', $requestXml);
        $this->assertStringContainsString('IdentityDocTypeCode', $requestXml);
        $this->assertStringNotContainsString('PaymentFunctions', $requestXml);
        $this->assertStringNotContainsString('AccountableDoc', $requestXml);

        File::deleteDirectory($directory);
        File::deleteDirectory($diagRoot);
    }

    public function test_execute_without_confirm_is_refused(): void
    {
        $connection = $this->piaConnection();
        $directory = $this->seedDiagnosticInput();

        $this->artisan('pia-ndc:order-create-diagnostic', array_merge(
            $this->validPassengerOptions(),
            [
                '--connection' => $connection->id,
                '--diagnostic-path' => $directory,
                '--execute-option-pnr' => true,
            ],
        ))
            ->expectsOutputToContain('CREATE_OPTION_PNR')
            ->assertFailed();

        File::deleteDirectory($directory);
    }

    public function test_missing_passenger_fields_are_refused(): void
    {
        $connection = $this->piaConnection();
        $directory = $this->seedDiagnosticInput();

        $this->artisan('pia-ndc:order-create-diagnostic', [
            '--connection' => $connection->id,
            '--diagnostic-path' => $directory,
        ])
            ->expectsOutputToContain('given-name')
            ->assertFailed();

        File::deleteDirectory($directory);
    }

    public function test_expired_payment_time_limit_refused_on_execute(): void
    {
        $connection = $this->piaConnection();
        $directory = $this->seedExpiredDiagnosticInput();

        $this->artisan('pia-ndc:order-create-diagnostic', array_merge(
            $this->validPassengerOptions(),
            [
                '--connection' => $connection->id,
                '--diagnostic-path' => $directory,
                '--execute-option-pnr' => true,
                '--confirm' => 'CREATE_OPTION_PNR',
            ],
        ))
            ->expectsOutputToContain('payment_time_limit has expired')
            ->assertFailed();

        File::deleteDirectory($directory);
    }

    public function test_execute_success_parses_order_reference_from_fixture(): void
    {
        $connection = $this->piaConnection();
        $directory = $this->seedDiagnosticInput();

        Http::fake([
            'example.test/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCreate_OW_res.xml')),
                200,
                ['Content-Type' => 'text/xml; charset=utf-8'],
            ),
        ]);

        $this->artisan('pia-ndc:order-create-diagnostic', array_merge(
            $this->validPassengerOptions(),
            [
                '--connection' => $connection->id,
                '--diagnostic-path' => $directory,
                '--execute-option-pnr' => true,
                '--confirm' => 'CREATE_OPTION_PNR',
            ],
        ))
            ->expectsOutputToContain('dry_run=false')
            ->expectsOutputToContain('supplier_called=true')
            ->expectsOutputToContain('success=true')
            ->expectsOutputToContain('order_id=7UU0J3')
            ->expectsOutputToContain('pnr=7UU0J3')
            ->expectsOutputToContain('booking_reference=7UU0J3')
            ->expectsOutputToContain('airline_locator=PK/7UU0J3')
            ->assertSuccessful();

        File::deleteDirectory($directory);
        File::deleteDirectory(storage_path('app/diagnostics/pia-ndc/order-create/'.$connection->id));
    }

    /**
     * @return array<string, string>
     */
    private function validPassengerOptions(): array
    {
        return [
            '--given-name' => 'JOHN',
            '--surname' => 'DOE',
            '--title' => 'MR',
            '--gender' => 'M',
            '--dob' => '1990-01-01',
            '--nationality' => 'PK',
            '--passport-number' => 'AB1234567',
            '--passport-expiry' => '2030-01-01',
            '--email' => 'john.doe@example.test',
            '--phone' => '+923001234567',
        ];
    }

    private function piaConnection(): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'provider' => SupplierProvider::PiaNdc,
            'base_url' => 'https://example.test/cranendc/v20.1/CraneNDCService',
            'credentials' => [
                'username' => 'test-user',
                'password' => 'test-pass',
                'agency_id' => 'SELENS',
                'agency_name' => 'NDC GATEWAY',
                'owner_code' => 'PK',
                'currency' => 'PKR',
                'language_code' => 'EN',
            ],
            'is_active' => true,
        ]);
    }

    private function seedDiagnosticInput(): string
    {
        $directory = storage_path('framework/testing/pia-ndc-order-create-input');
        File::ensureDirectoryExists($directory);
        File::copy(
            base_path('tests/Fixtures/pia-ndc/order_create_normalized_input.json'),
            $directory.'/normalized.json',
        );

        return $directory;
    }

    private function seedExpiredDiagnosticInput(): string
    {
        $directory = storage_path('framework/testing/pia-ndc-order-create-expired');
        File::ensureDirectoryExists($directory);
        File::copy(
            base_path('tests/Fixtures/pia-ndc/order_create_expired_input.json'),
            $directory.'/normalized.json',
        );

        return $directory;
    }
}
