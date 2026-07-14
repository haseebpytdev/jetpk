<?php

namespace Tests\Unit\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\PiaNdcReleaseExecutionLock;
use App\Services\Suppliers\PiaNdc\PiaNdcReleaseOptionPnrService;
use App\Support\Bookings\AdminPiaNdcReleaseOptionPnrPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class PiaNdcReleaseOptionPnrCommandTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_dry_run_calls_retrieve_and_preview_but_not_commit(): void
    {
        $connection = $this->piaConnection();
        $diagRoot = storage_path('app/diagnostics/pia-ndc/release-option-pnr/'.$connection->id);
        File::deleteDirectory($diagRoot);
        $this->clearReleaseLocks();

        Http::fake([
            'example.test/*' => Http::sequence()
                ->push(file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderRetrieve_OW_res.xml')), 200, ['Content-Type' => 'text/xml; charset=utf-8'])
                ->push(file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCancelPreview_OW_res.xml')), 200, ['Content-Type' => 'text/xml; charset=utf-8']),
        ]);

        $this->artisan('pia-ndc:release-option-pnr', [
            '--connection' => $connection->id,
            '--order-id' => '9FCSLN',
            '--owner-code' => 'PK',
            '--reason' => 'dry-run validation',
        ])
            ->expectsOutputToContain('dry_run=true')
            ->expectsOutputToContain('retrieve_success=true')
            ->expectsOutputToContain('preview_success=true')
            ->expectsOutputToContain('commit_success=')
            ->assertSuccessful();

        Http::assertSentCount(2);

        $folders = File::directories($diagRoot);
        $this->assertNotEmpty($folders);
        $this->assertFileExists($folders[0].'/commit-request.xml');
        $this->assertFileDoesNotExist($folders[0].'/commit-response.xml');
        $summary = json_decode(file_get_contents($folders[0].'/summary.json') ?: '', true);
        $this->assertIsArray($summary);
        $this->assertTrue($summary['dry_run'] ?? false);
        $this->assertFalse($summary['commit_supplier_called'] ?? true);

        File::deleteDirectory($diagRoot);
    }

    public function test_execute_requires_confirm_phrase(): void
    {
        $connection = $this->piaConnection();

        $this->artisan('pia-ndc:release-option-pnr', [
            '--connection' => $connection->id,
            '--order-id' => '9FCSLN',
            '--execute-release' => true,
            '--reason' => 'cleanup',
        ])
            ->expectsOutputToContain('RELEASE_PIA_OPTION_PNR')
            ->assertFailed();
    }

    public function test_execute_requires_reason(): void
    {
        $connection = $this->piaConnection();

        $this->artisan('pia-ndc:release-option-pnr', [
            '--connection' => $connection->id,
            '--order-id' => '9FCSLN',
            '--execute-release' => true,
            '--confirm' => PiaNdcReleaseOptionPnrService::RELEASE_CONFIRM_PHRASE,
        ])
            ->expectsOutputToContain('operator note')
            ->assertFailed();
    }

    public function test_execute_blocks_ticketed_order(): void
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

        $this->artisan('pia-ndc:release-option-pnr', [
            '--connection' => $connection->id,
            '--order-id' => '9FCSLN',
            '--execute-release' => true,
            '--confirm' => PiaNdcReleaseOptionPnrService::RELEASE_CONFIRM_PHRASE,
            '--reason' => 'cleanup',
        ])
            ->expectsOutputToContain('issued ticket')
            ->assertFailed();

        Http::assertSentCount(1);
    }

    public function test_blocks_when_retrieve_fails(): void
    {
        $connection = $this->piaConnection();
        $faultXml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCancelCommit_fault_500.xml'));

        Http::fake([
            'example.test/*' => Http::response($faultXml, 500, ['Content-Type' => 'text/xml; charset=utf-8']),
        ]);

        $this->artisan('pia-ndc:release-option-pnr', [
            '--connection' => $connection->id,
            '--order-id' => '9FCSLN',
            '--reason' => 'cleanup',
        ])
            ->expectsOutputToContain('retrieve did not succeed')
            ->assertFailed();
    }

    public function test_blocks_when_preview_fails(): void
    {
        $connection = $this->piaConnection();
        $retrieveXml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderRetrieve_OW_res.xml'));
        $faultXml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCancelCommit_fault_500.xml'));

        Http::fake([
            'example.test/*' => Http::sequence()
                ->push($retrieveXml, 200, ['Content-Type' => 'text/xml; charset=utf-8'])
                ->push($faultXml, 500, ['Content-Type' => 'text/xml; charset=utf-8']),
        ]);

        $this->artisan('pia-ndc:release-option-pnr', [
            '--connection' => $connection->id,
            '--order-id' => '9FCSLN',
            '--reason' => 'cleanup',
        ])
            ->expectsOutputToContain('cancel preview did not succeed')
            ->assertFailed();
    }

    public function test_blocks_duplicate_release_after_commit(): void
    {
        $connection = $this->piaConnection();
        $this->clearReleaseLocks();
        $lock = app(PiaNdcReleaseExecutionLock::class);
        $lockPath = $lock->acquire($connection->id, '9FCSLN', 'PK', 'prior-correlation');
        $lock->markCommitted($lockPath, true, 'prior-correlation');

        $this->artisan('pia-ndc:release-option-pnr', [
            '--connection' => $connection->id,
            '--order-id' => '9FCSLN',
            '--execute-release' => true,
            '--confirm' => PiaNdcReleaseOptionPnrService::RELEASE_CONFIRM_PHRASE,
            '--reason' => 'cleanup',
        ])
            ->expectsOutputToContain('already committed')
            ->assertFailed();

        Http::assertNothingSent();
        $this->clearReleaseLocks();
    }

    public function test_execute_writes_sanitized_diagnostics(): void
    {
        $connection = $this->piaConnection();
        $diagRoot = storage_path('app/diagnostics/pia-ndc/release-option-pnr/'.$connection->id);
        File::deleteDirectory($diagRoot);
        $this->clearReleaseLocks();

        Http::fake([
            'example.test/*' => Http::sequence()
                ->push(file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderRetrieve_OW_res.xml')), 200, ['Content-Type' => 'text/xml; charset=utf-8'])
                ->push(file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCancelPreview_OW_res.xml')), 200, ['Content-Type' => 'text/xml; charset=utf-8'])
                ->push(file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCancelCommit_OW_res.xml')), 200, ['Content-Type' => 'text/xml; charset=utf-8'])
                ->push(file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderRetrieve_OW_res.xml')), 200, ['Content-Type' => 'text/xml; charset=utf-8']),
        ]);

        $this->artisan('pia-ndc:release-option-pnr', [
            '--connection' => $connection->id,
            '--order-id' => '9FCSLN',
            '--owner-code' => 'PK',
            '--execute-release' => true,
            '--confirm' => PiaNdcReleaseOptionPnrService::RELEASE_CONFIRM_PHRASE,
            '--reason' => 'controlled cleanup after test',
        ])
            ->expectsOutputToContain('commit_success=true')
            ->expectsOutputToContain('cancellation_status=cancelled')
            ->assertSuccessful();

        Http::assertSentCount(4);

        $folders = File::directories($diagRoot);
        $folder = $folders[0];
        $this->assertFileExists($folder.'/summary.json');
        $this->assertFileExists($folder.'/commit-request.xml');
        $this->assertFileExists($folder.'/commit-response.xml');
        $requestXml = file_get_contents($folder.'/commit-request.xml') ?: '';
        $this->assertStringNotContainsString('Password', $requestXml);
        $this->assertStringContainsString('CancelOrder', $requestXml);

        File::deleteDirectory($diagRoot);
        $this->clearReleaseLocks();
    }

    public function test_admin_panel_hidden_for_non_pia_booking(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'meta' => ['supplier_provider' => 'sabre'],
        ]);

        $panel = app(AdminPiaNdcReleaseOptionPnrPresenter::class)->panel($booking);

        $this->assertFalse($panel['show']);
    }

    public function test_admin_panel_hides_release_for_ticketed_pia_booking(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::PiaNdc->value,
            'status' => BookingStatus::Ticketed,
            'meta' => [
                'supplier_provider' => SupplierProvider::PiaNdc->value,
                'pia_ndc_context' => [
                    'order_id' => '9FCSLN',
                    'owner_code' => 'PK',
                    'ticket_numbers' => ['1761234567890'],
                ],
            ],
        ]);

        $panel = app(AdminPiaNdcReleaseOptionPnrPresenter::class)->panel($booking);

        $this->assertTrue($panel['show']);
        $this->assertFalse($panel['can_release']);
    }

    public function test_admin_release_uses_release_service(): void
    {
        $connection = $this->piaConnection();
        $admin = $this->platformAdmin();
        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::PiaNdc->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::PiaNdc->value,
                'supplier_connection_id' => $connection->id,
                'pia_ndc_context' => [
                    'order_id' => '9FCSLN',
                    'owner_code' => 'PK',
                ],
            ],
        ]);

        Http::fake([
            'example.test/*' => Http::sequence()
                ->push(file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderRetrieve_OW_res.xml')), 200, ['Content-Type' => 'text/xml; charset=utf-8'])
                ->push(file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderRetrieve_OW_res.xml')), 200, ['Content-Type' => 'text/xml; charset=utf-8'])
                ->push(file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCancelPreview_OW_res.xml')), 200, ['Content-Type' => 'text/xml; charset=utf-8'])
                ->push(file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCancelCommit_OW_res.xml')), 200, ['Content-Type' => 'text/xml; charset=utf-8'])
                ->push(file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderRetrieve_OW_res.xml')), 200, ['Content-Type' => 'text/xml; charset=utf-8']),
        ]);

        $this->clearReleaseLocks();

        $response = $this->actingAs($admin)->post(route('admin.bookings.release-pia-ndc-option-pnr', $booking), [
            'confirm_phrase' => PiaNdcReleaseOptionPnrService::RELEASE_CONFIRM_PHRASE,
            'operator_reason' => 'admin controlled release',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $this->assertTrue($context['option_pnr_released'] ?? false);
        $this->assertSame('released', $booking->supplier_booking_status);

        $this->assertDatabaseHas('supplier_booking_attempts', [
            'booking_id' => $booking->id,
            'action' => 'pia_ndc_release_option_pnr',
            'status' => 'success',
        ]);

        $this->clearReleaseLocks();
    }

    private function clearReleaseLocks(): void
    {
        File::deleteDirectory(storage_path('app/diagnostics/pia-ndc/release-option-pnr-locks'));
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
