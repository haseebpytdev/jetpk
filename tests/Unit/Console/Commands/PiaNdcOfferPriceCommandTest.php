<?php

namespace Tests\Unit\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PiaNdcOfferPriceCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reads_provider_context_from_diagnostic_normalized_json(): void
    {
        $connection = $this->piaConnection();
        $directory = $this->seedDiagnosticInput();

        Http::fake([
            'example.test/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/pia-ndc/doOfferPrice_OW_res.xml')),
                200,
                ['Content-Type' => 'text/xml; charset=utf-8'],
            ),
        ]);

        $this->artisan('pia-ndc:offer-price', [
            '--connection' => $connection->id,
            '--diagnostic-path' => $directory,
            '--offer-index' => 0,
        ])
            ->expectsOutputToContain('success=true')
            ->expectsOutputToContain('commercially_valid_price=true')
            ->expectsOutputToContain('shopping_response_ref_id=b00fe7be-88f0-4de3-b455-28b5aa20f767')
            ->expectsOutputToContain('offer_item_ref_id=OfferItem-13')
            ->expectsOutputToContain('total_amount=44510')
            ->expectsOutputToContain('fare_changed=false')
            ->assertSuccessful();

        File::deleteDirectory($directory);
    }

    public function test_zero_price_response_marks_success_false(): void
    {
        $connection = $this->piaConnection();
        $directory = $this->seedDiagnosticInput();

        Http::fake([
            'example.test/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/pia-ndc/doOfferPrice_zero_res.xml')),
                200,
                ['Content-Type' => 'text/xml; charset=utf-8'],
            ),
        ]);

        $this->artisan('pia-ndc:offer-price', [
            '--connection' => $connection->id,
            '--diagnostic-path' => $directory,
            '--offer-index' => 0,
        ])
            ->expectsOutputToContain('success=false')
            ->expectsOutputToContain('zero_price=true')
            ->expectsOutputToContain('total_amount=0')
            ->assertFailed();

        File::deleteDirectory($directory);
    }

    public function test_probe_shapes_prints_multiple_shape_rows(): void
    {
        $connection = $this->piaConnection();
        $directory = $this->seedDiagnosticInput();

        Http::fake([
            'example.test/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/pia-ndc/doOfferPrice_zero_res.xml')),
                200,
                ['Content-Type' => 'text/xml; charset=utf-8'],
            ),
        ]);

        $this->artisan('pia-ndc:offer-price', [
            '--connection' => $connection->id,
            '--diagnostic-path' => $directory,
            '--offer-index' => 0,
            '--probe-shapes' => true,
        ])
            ->expectsOutputToContain('shape=current_priced_offer_selected_offer')
            ->expectsOutputToContain('shape=selected_offer_without_priced_offer_wrapper')
            ->expectsOutputToContain('shape=selected_offer_with_shopping_response_object')
            ->expectsOutputToContain('zero_price=true')
            ->expectsOutputToContain('success=false')
            ->assertFailed();

        File::deleteDirectory($directory);
    }

    public function test_fee_only_response_marks_success_false(): void
    {
        $connection = $this->piaConnection();
        $directory = $this->seedDiagnosticInput(31131.0);

        Http::fake([
            'example.test/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/pia-ndc/doOfferPrice_fee_only_res.xml')),
                200,
                ['Content-Type' => 'text/xml; charset=utf-8'],
            ),
        ]);

        $this->artisan('pia-ndc:offer-price', [
            '--connection' => $connection->id,
            '--diagnostic-path' => $directory,
            '--offer-index' => 0,
        ])
            ->expectsOutputToContain('success=false')
            ->expectsOutputToContain('fee_only_price=true')
            ->expectsOutputToContain('partial_price=true')
            ->expectsOutputToContain('commercially_valid_price=false')
            ->expectsOutputToContain('offer_price_total=1484')
            ->expectsOutputToContain('fee_amount_total=1484')
            ->expectsOutputToContain('air_shopping_total=31131')
            ->assertFailed();

        File::deleteDirectory($directory);
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

    private function seedDiagnosticInput(?float $supplierTotal = 44510.0): string
    {
        $directory = storage_path('framework/testing/pia-ndc-offer-price-input');
        File::ensureDirectoryExists($directory);
        $payload = json_decode(
            file_get_contents(base_path('tests/Fixtures/pia-ndc/offer_price_normalized_input.json')) ?: '{}',
            true,
        );
        if (is_array($payload) && $supplierTotal !== null) {
            $payload['offers'][0]['fare_breakdown']['supplier_total'] = $supplierTotal;
        }
        file_put_contents(
            $directory.'/normalized.json',
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        return $directory;
    }
}
