<?php

namespace Tests\Unit\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\PiaNdcResponseNormalizer;
use App\Services\Suppliers\PiaNdc\PiaNdcXmlBuilder;
use App\Services\Suppliers\PiaNdc\PiaNdcXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PiaNdcOrderRetrieveCancelDiagnosticCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_retrieve_request_builds_with_order_id(): void
    {
        $connection = $this->piaConnection();
        $diagRoot = storage_path('app/diagnostics/pia-ndc/order-retrieve/'.$connection->id);
        File::deleteDirectory($diagRoot);

        Http::fake([
            'example.test/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderRetrieve_OW_res.xml')),
                200,
                ['Content-Type' => 'text/xml; charset=utf-8'],
            ),
        ]);

        $this->artisan('pia-ndc:order-retrieve-diagnostic', [
            '--connection' => $connection->id,
            '--order-id' => '7UU0J3',
            '--owner-code' => 'PK',
        ])
            ->expectsOutputToContain('order_id=7UU0J3')
            ->expectsOutputToContain('success=true')
            ->assertSuccessful();

        $folders = File::directories($diagRoot);
        $this->assertNotEmpty($folders);
        $requestXml = file_get_contents($folders[0].'/request.xml') ?: '';
        $this->assertStringContainsString('7UU0J3', $requestXml);
        $this->assertStringContainsString('OrderID', $requestXml);
        $this->assertStringContainsString('OwnerCode', $requestXml);

        File::deleteDirectory($diagRoot);
    }

    public function test_cancel_dry_run_does_not_call_supplier(): void
    {
        $connection = $this->piaConnection();
        $diagRoot = storage_path('app/diagnostics/pia-ndc/order-cancel/'.$connection->id);
        File::deleteDirectory($diagRoot);

        Http::fake();

        $this->artisan('pia-ndc:order-cancel-diagnostic', [
            '--connection' => $connection->id,
            '--pnr' => '9FCPZ3',
            '--owner-code' => 'PK',
        ])
            ->expectsOutputToContain('dry_run=true')
            ->expectsOutputToContain('supplier_called=false')
            ->expectsOutputToContain('success=true')
            ->assertSuccessful();

        Http::assertNothingSent();

        $folders = File::directories($diagRoot);
        $requestXml = file_get_contents($folders[0].'/request.xml') ?: '';
        $this->assertStringContainsString('9FCPZ3', $requestXml);
        $this->assertStringContainsString('CancelOrder', $requestXml);
        $this->assertStringNotContainsString('PaymentFunctions', $requestXml);
        $this->assertStringNotContainsString('AccountableDoc', $requestXml);

        File::deleteDirectory($diagRoot);
    }

    public function test_cancel_execute_without_confirm_is_refused(): void
    {
        $connection = $this->piaConnection();

        $this->artisan('pia-ndc:order-cancel-diagnostic', [
            '--connection' => $connection->id,
            '--order-id' => '9FCPZ3',
            '--execute-cancel' => true,
            '--shape' => 'current_order_change_cancel_order',
        ])
            ->expectsOutputToContain('PREVIEW_OPTION_PNR')
            ->assertFailed();
    }

    public function test_cancel_execute_without_shape_is_refused(): void
    {
        $connection = $this->piaConnection();

        $this->artisan('pia-ndc:order-cancel-diagnostic', [
            '--connection' => $connection->id,
            '--order-id' => '9FCPZ3',
            '--execute-cancel' => true,
            '--confirm' => 'CANCEL_OPTION_PNR',
        ])
            ->expectsOutputToContain('--shape=')
            ->assertFailed();
    }

    public function test_cancel_probe_shapes_does_not_call_supplier(): void
    {
        $connection = $this->piaConnection();
        $diagRoot = storage_path('app/diagnostics/pia-ndc/order-cancel-probe/'.$connection->id);
        File::deleteDirectory($diagRoot);

        Http::fake();

        $this->artisan('pia-ndc:order-cancel-diagnostic', [
            '--connection' => $connection->id,
            '--order-id' => '9FCPZ3',
            '--owner-code' => 'PK',
            '--probe-shapes' => true,
        ])
            ->expectsOutputToContain('probe_shapes=true')
            ->expectsOutputToContain('supplier_called=false')
            ->assertSuccessful();

        Http::assertNothingSent();

        $folders = File::directories($diagRoot);
        $this->assertNotEmpty($folders);
        $variantFolders = File::directories($folders[0]);
        $this->assertGreaterThanOrEqual(count(PiaNdcXmlBuilder::CANCEL_PROBE_SHAPES), count($variantFolders));

        File::deleteDirectory($diagRoot);
    }

    public function test_cancel_commit_execute_blocked_in_r11e(): void
    {
        $connection = $this->piaConnection();

        $this->artisan('pia-ndc:order-cancel-diagnostic', [
            '--connection' => $connection->id,
            '--order-id' => '9FCPZ3',
            '--owner-code' => 'PK',
            '--execute-cancel' => true,
            '--confirm' => 'CANCEL_OPTION_PNR',
            '--shape' => 'current_order_change_cancel_order',
            '--operation' => 'doOrderCancelCommit',
        ])
            ->expectsOutputToContain('doOrderCancelCommit execution is blocked')
            ->assertFailed();
    }

    public function test_cancel_preview_http_500_soap_fault_does_not_mark_cancelled(): void
    {
        $connection = $this->piaConnection();
        $diagRoot = storage_path('app/diagnostics/pia-ndc/order-cancel/'.$connection->id);
        File::deleteDirectory($diagRoot);
        $this->clearCancelExecutionLocks();
        $retrieveXml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderRetrieve_OW_res.xml'));
        $faultXml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCancelCommit_fault_500.xml'));

        Http::fake([
            'example.test/*' => Http::sequence()
                ->push($retrieveXml, 200, ['Content-Type' => 'text/xml; charset=utf-8'])
                ->push($faultXml, 500, ['Content-Type' => 'text/xml; charset=utf-8']),
        ]);

        $this->artisan('pia-ndc:order-cancel-diagnostic', [
            '--connection' => $connection->id,
            '--order-id' => '9FCPZ3',
            '--owner-code' => 'PK',
            '--execute-cancel' => true,
            '--confirm' => 'PREVIEW_OPTION_PNR',
            '--shape' => 'hitit_cancel_preview_sample_exact',
        ])
            ->expectsOutputToContain('success=false')
            ->expectsOutputToContain('cancel_preview_status=failed')
            ->expectsOutputToContain('soap_fault_string=java.lang.NullPointerException')
            ->assertFailed();

        $folders = File::directories($diagRoot);
        $summary = json_decode(file_get_contents($folders[0].'/summary.json') ?: '', true);
        $this->assertIsArray($summary);
        $this->assertArrayNotHasKey('cancellation_status', $summary);

        File::deleteDirectory($diagRoot);
    }

    public function test_cancel_execute_blocks_ticketed_order(): void
    {
        $connection = $this->piaConnection();
        $ticketedRetrieve = str_replace(
            'FakeTicket1',
            '1761234567890',
            file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCreate_OW_res.xml')) ?: '',
        );

        Http::fake([
            'example.test/*' => Http::response($ticketedRetrieve, 200, ['Content-Type' => 'text/xml; charset=utf-8']),
        ]);

        $this->artisan('pia-ndc:order-cancel-diagnostic', [
            '--connection' => $connection->id,
            '--order-id' => '9FCPZ3',
            '--execute-cancel' => true,
            '--confirm' => 'PREVIEW_OPTION_PNR',
            '--shape' => 'hitit_cancel_preview_sample_exact',
        ])
            ->expectsOutputToContain('issued ticket')
            ->assertFailed();

        Http::assertSentCount(1);
    }

    public function test_retrieve_fixture_does_not_use_service_hk_as_order_status(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderRetrieve_OW_res.xml'));
        $parser = app(PiaNdcXmlParser::class);
        $parsed = $parser->parse($xml ?: '');
        $normalized = app(PiaNdcResponseNormalizer::class)
            ->normalizeOrderRetrieveDiagnosticResponse($parsed, ['owner_code' => 'PK']);

        $this->assertNull($normalized['order_status']);
        $this->assertContains('HK', $normalized['service_statuses']);
        $this->assertContains('ENTITLED', $normalized['order_item_statuses']);
        $this->assertNotSame('HK', $normalized['order_status']);
    }

    public function test_sample_exact_cancel_shape_matches_hitit_fixture_structure(): void
    {
        $builder = new PiaNdcXmlBuilder;
        $config = [
            'agency_id' => 'SELENS',
            'agency_name' => 'NDC GATEWAY',
            'currency' => 'PKR',
            'language_code' => 'EN',
            'owner_code' => 'PK',
        ];
        $xml = $builder->buildCancelDiagnosticRequest($config, '7UU0JB', 'S5', 'sample_exact_cancel_request');

        $this->assertStringContainsString('IATA_OrderChangeRQ', $xml);
        $this->assertStringContainsString('<CancelOrder>', $xml);
        $this->assertStringContainsString('<OrderRefID>7UU0JB</OrderRefID>', $xml);
        $this->assertStringContainsString('<OrderID>7UU0JB</OrderID>', $xml);
        $this->assertStringContainsString('<OwnerCode>S5</OwnerCode>', $xml);
        $this->assertStringNotContainsString('PaymentFunctions', $xml);
        $this->assertStringNotContainsString('AccountableDoc', $xml);
    }

    public function test_retrieve_parser_extracts_order_status_and_booking_reference(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCreate_OW_res.xml'));
        $parser = app(PiaNdcXmlParser::class);
        $parsed = $parser->parse($xml ?: '');
        $normalized = app(PiaNdcResponseNormalizer::class)
            ->normalizeOrderRetrieveDiagnosticResponse($parsed, ['owner_code' => 'PK']);

        $this->assertSame('7UU0J3', $normalized['order_id']);
        $this->assertSame('7UU0J3', $normalized['booking_reference']);
        $this->assertSame('PK/7UU0J3', $normalized['airline_locator']);
        $this->assertSame('OPENED', $normalized['order_status']);
        $this->assertGreaterThan(0, $normalized['segment_count']);
        $this->assertGreaterThan(0, $normalized['passenger_count']);
    }

    public function test_fake_ticket_does_not_block_cancel_guard(): void
    {
        $normalizer = app(PiaNdcResponseNormalizer::class);
        $this->assertFalse($normalizer->hasBlockingTicketNumbers([
            ['ticket_number' => 'FakeTicket1'],
        ]));
        $this->assertTrue($normalizer->hasBlockingTicketNumbers([
            ['ticket_number' => '1761234567890'],
        ]));
    }

    private function clearCancelExecutionLocks(): void
    {
        foreach ([
            'order-cancel-commit-locks',
            'order-cancel-preview-locks',
            'order-cancel-locks',
        ] as $subdir) {
            File::deleteDirectory(storage_path('app/diagnostics/pia-ndc/'.$subdir));
        }
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
