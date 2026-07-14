<?php

namespace Tests\Unit\Services\Suppliers\PiaNdc;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\PiaNdcClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PiaNdcClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_preview_short_soap_action_is_normalized_for_http_header(): void
    {
        $connection = $this->piaConnection();
        $client = app(PiaNdcClient::class);
        $requestXml = '<IATA_OrderChangeRQ/>';
        $responseXml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderRetrieve_OW_res.xml'));

        Http::fake([
            'example.test/*' => Http::response($responseXml, 200, ['Content-Type' => 'text/xml; charset=utf-8']),
        ]);

        $parsed = $client->call($connection, 'cancel_preview', $requestXml, [
            'soap_action_override' => 'doOrderCancelPreview',
        ]);

        Http::assertSent(function (Request $request): bool {
            return $request->header('SOAPAction')[0] === '"cranendc/doOrderCancelPreview"';
        });

        $this->assertSame('doOrderCancelPreview', $parsed['_ota_diagnostic']['soap_action_configured']);
        $this->assertSame('"cranendc/doOrderCancelPreview"', $parsed['_ota_diagnostic']['soap_action_sent']);
    }

    public function test_already_prefixed_soap_action_is_not_double_prefixed(): void
    {
        $connection = $this->piaConnection();
        $client = app(PiaNdcClient::class);
        $responseXml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderRetrieve_OW_res.xml'));

        Http::fake([
            'example.test/*' => Http::response($responseXml, 200, ['Content-Type' => 'text/xml; charset=utf-8']),
        ]);

        $parsed = $client->call($connection, 'cancel_preview', '<IATA_OrderChangeRQ/>', [
            'soap_action_override' => 'cranendc/doOrderCancelPreview',
        ]);

        Http::assertSent(function (Request $request): bool {
            return $request->header('SOAPAction')[0] === '"cranendc/doOrderCancelPreview"';
        });

        $this->assertSame('cranendc/doOrderCancelPreview', $parsed['_ota_diagnostic']['soap_action_configured']);
        $this->assertSame('"cranendc/doOrderCancelPreview"', $parsed['_ota_diagnostic']['soap_action_sent']);
    }

    public function test_quoted_soap_action_override_is_normalized(): void
    {
        $connection = $this->piaConnection();
        $client = app(PiaNdcClient::class);
        $responseXml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderRetrieve_OW_res.xml'));

        Http::fake([
            'example.test/*' => Http::response($responseXml, 200, ['Content-Type' => 'text/xml; charset=utf-8']),
        ]);

        $client->call($connection, 'cancel_preview', '<IATA_OrderChangeRQ/>', [
            'soap_action_override' => ' "doOrderCancelPreview" ',
        ]);

        Http::assertSent(function (Request $request): bool {
            return $request->header('SOAPAction')[0] === '"cranendc/doOrderCancelPreview"';
        });
    }

    public function test_order_create_uses_configured_short_action_and_sends_cranendc_prefix(): void
    {
        $connection = $this->piaConnection();
        $client = app(PiaNdcClient::class);
        $responseXml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderRetrieve_OW_res.xml'));

        Http::fake([
            'example.test/*' => Http::response($responseXml, 200, ['Content-Type' => 'text/xml; charset=utf-8']),
        ]);

        $parsed = $client->call($connection, 'order_create', '<IATA_OrderCreateRQ/>');

        Http::assertSent(function (Request $request): bool {
            return $request->header('SOAPAction')[0] === '"cranendc/doOrderCreate"';
        });

        $this->assertSame('doOrderCreate', $parsed['_ota_diagnostic']['soap_action_configured']);
        $this->assertSame('"cranendc/doOrderCreate"', $parsed['_ota_diagnostic']['soap_action_sent']);
    }

    public function test_cancel_commit_sends_cranendc_prefixed_soap_action(): void
    {
        $connection = $this->piaConnection();
        $client = app(PiaNdcClient::class);
        $responseXml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderRetrieve_OW_res.xml'));

        Http::fake([
            'example.test/*' => Http::response($responseXml, 200, ['Content-Type' => 'text/xml; charset=utf-8']),
        ]);

        $parsed = $client->call($connection, 'cancel_commit', '<IATA_OrderChangeRQ/>');

        Http::assertSent(function (Request $request): bool {
            return $request->header('SOAPAction')[0] === '"cranendc/doOrderCancelCommit"';
        });

        $this->assertSame('doOrderCancelCommit', $parsed['_ota_diagnostic']['soap_action_configured']);
        $this->assertSame('"cranendc/doOrderCancelCommit"', $parsed['_ota_diagnostic']['soap_action_sent']);
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
}
