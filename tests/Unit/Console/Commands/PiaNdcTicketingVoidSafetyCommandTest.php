<?php

namespace Tests\Unit\Console\Commands;

use App\Enums\AccountType;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Support\Bookings\PiaNdcOperationAuditRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class PiaNdcTicketingVoidSafetyCommandTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['ticket-preview', 'ticketing', 'void-ticket'] as $operation) {
            File::deleteDirectory(storage_path('app/diagnostics/pia-ndc/'.$operation));
        }
    }

    public function test_ticket_preview_default_is_dry_run_and_no_supplier_call(): void
    {
        Http::fake();
        [$booking] = $this->piaBooking();

        $this->artisan('pia-ndc:test-ticket-preview', ['booking' => $booking->id])
            ->expectsOutputToContain('dry_run=true')
            ->expectsOutputToContain('supplier_called=false')
            ->expectsOutputToContain('operation=doTicketPreview')
            ->expectsOutputToContain('request_built=true')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_ticket_preview_execute_requires_confirm(): void
    {
        [$booking] = $this->piaBooking();

        $this->artisan('pia-ndc:test-ticket-preview', [
            'booking' => $booking->id,
            '--execute-preview' => true,
        ])
            ->expectsOutputToContain('PREVIEW_PIA_NDC_TICKET')
            ->assertFailed();
    }

    public function test_ticketing_default_is_dry_run_and_no_supplier_call(): void
    {
        Http::fake();
        $admin = $this->platformAdmin();
        [$booking] = $this->piaBooking([
            'ticket_preview' => ['amount' => 44510.0, 'currency' => 'PKR'],
        ]);

        $this->artisan('pia-ndc:test-ticketing', ['booking' => $booking->id])
            ->expectsOutputToContain('dry_run=true')
            ->expectsOutputToContain('supplier_called=false')
            ->expectsOutputToContain('operation=doOrderChange')
            ->expectsOutputToContain('request_built=true')
            ->expectsOutputToContain('actor_id='.$admin->id)
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_ticketing_execute_requires_confirm(): void
    {
        $this->platformAdmin();
        [$booking] = $this->piaBooking();

        $this->artisan('pia-ndc:test-ticketing', [
            'booking' => $booking->id,
            '--execute-ticketing' => true,
        ])
            ->expectsOutputToContain('ISSUE_PIA_NDC_TICKET')
            ->assertFailed();
    }

    public function test_ticketing_blocks_when_ticket_numbers_already_exist(): void
    {
        Http::fake();
        $this->platformAdmin();
        [$booking] = $this->piaBooking([
            'ticket_numbers' => ['1761234567890'],
            'ticketing_status' => 'ticketed',
        ]);

        $this->artisan('pia-ndc:test-ticketing', ['booking' => $booking->id])
            ->expectsOutputToContain('duplicate_ticket_guard=true')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_ticketing_blocks_when_payment_time_limit_expired(): void
    {
        $this->platformAdmin();
        [$booking, $connection] = $this->piaBooking([
            'ticket_preview' => ['amount' => 44510.0, 'currency' => 'PKR'],
        ]);

        Http::fake([
            'example.test/*' => Http::response(
                $this->retrieveXmlWithPaymentLimit('2020-01-01T00:00:00+05:00'),
                200,
                ['Content-Type' => 'text/xml; charset=utf-8'],
            ),
        ]);

        $this->artisan('pia-ndc:test-ticketing', [
            'booking' => $booking->id,
            '--connection' => $connection->id,
            '--execute-ticketing' => true,
            '--confirm' => 'ISSUE_PIA_NDC_TICKET',
        ])
            ->expectsOutputToContain('payment_time_limit_expired')
            ->assertFailed();
    }

    public function test_ticketing_requires_actor_or_admin_actor(): void
    {
        [$booking] = $this->piaBooking([
            'ticket_preview' => ['amount' => 44510.0, 'currency' => 'PKR'],
        ]);

        User::query()->where('account_type', AccountType::PlatformAdmin)->delete();

        $this->artisan('pia-ndc:test-ticketing', ['booking' => $booking->id])
            ->expectsOutputToContain('actor_required')
            ->assertFailed();
    }

    public function test_ticketing_live_runs_fresh_retrieve_before_order_change(): void
    {
        $this->platformAdmin();
        [$booking, $connection] = $this->piaBooking([
            'ticket_preview' => ['amount' => 44510.0, 'currency' => 'PKR'],
        ]);

        $retrieveXml = $this->retrieveXmlWithPaymentLimit('2099-12-31T23:59:59+05:00');
        $ticketingXml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderChange_OW_res.xml')) ?: '';
        $calls = [];

        Http::fake(function ($request) use (&$calls, $retrieveXml, $ticketingXml) {
            $calls[] = (string) $request->body();

            if (str_contains((string) $request->body(), 'OrderRetrieve')) {
                return Http::response($retrieveXml, 200, ['Content-Type' => 'text/xml; charset=utf-8']);
            }

            return Http::response($ticketingXml, 200, ['Content-Type' => 'text/xml; charset=utf-8']);
        });

        $this->artisan('pia-ndc:test-ticketing', [
            'booking' => $booking->id,
            '--connection' => $connection->id,
            '--execute-ticketing' => true,
            '--confirm' => 'ISSUE_PIA_NDC_TICKET',
        ])->assertSuccessful();

        $this->assertGreaterThanOrEqual(2, count($calls));
        $this->assertTrue(str_contains($calls[0], 'OrderRetrieve'));
        $this->assertTrue(str_contains($calls[1], 'OrderChange') || str_contains($calls[1], 'ChangeOrder'));

        $booking->refresh();
        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', PiaNdcOperationAuditRecorder::ACTION_TICKETING)
            ->latest('id')
            ->first();
        $this->assertNotNull($attempt);
        $this->assertSame('success', $attempt->status);
        $this->assertIsArray($booking->meta[PiaNdcOperationAuditRecorder::META_TICKETING] ?? null);
        $this->assertSame('success', $booking->meta[PiaNdcOperationAuditRecorder::META_TICKETING]['status'] ?? null);
        $this->assertSame('doOrderChange', $booking->meta[PiaNdcOperationAuditRecorder::META_TICKETING]['operation'] ?? null);
    }

    public function test_live_ticket_preview_persists_audit_sidecar_and_attempt(): void
    {
        [$booking, $connection] = $this->piaBooking();
        $previewXml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doTicketPreview_OW_res.xml')) ?: '';

        Http::fake([
            'example.test/*' => Http::response($previewXml, 200, ['Content-Type' => 'text/xml; charset=utf-8']),
        ]);

        $this->artisan('pia-ndc:test-ticket-preview', [
            'booking' => $booking->id,
            '--connection' => $connection->id,
            '--execute-preview' => true,
            '--confirm' => 'PREVIEW_PIA_NDC_TICKET',
        ])->assertSuccessful();

        $booking->refresh();
        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', PiaNdcOperationAuditRecorder::ACTION_TICKET_PREVIEW)
            ->latest('id')
            ->first();
        $this->assertNotNull($attempt);
        $this->assertSame('success', $attempt->status);
        $this->assertIsArray($booking->meta[PiaNdcOperationAuditRecorder::META_TICKET_PREVIEW] ?? null);
        $this->assertSame('doTicketPreview', $booking->meta[PiaNdcOperationAuditRecorder::META_TICKET_PREVIEW]['operation'] ?? null);
    }

    public function test_live_void_persists_audit_sidecar_and_attempt(): void
    {
        $this->platformAdmin();
        [$booking, $connection] = $this->piaBooking([
            'ticket_numbers' => ['1761234567890'],
            'ticketing_status' => 'ticketed',
        ]);

        $retrieveXml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderChange_OW_res.xml')) ?: '';
        $retrieveXml = str_replace('2025-05-27T15:17:52+05:00', '2099-12-31T23:59:59+05:00', $retrieveXml);
        $voidXml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doVoidTicket_res.xml')) ?: '';

        Http::fake(function ($request) use ($retrieveXml, $voidXml) {
            if (str_contains((string) $request->body(), 'OrderRetrieve')) {
                return Http::response($retrieveXml, 200, ['Content-Type' => 'text/xml; charset=utf-8']);
            }

            return Http::response($voidXml, 200, ['Content-Type' => 'text/xml; charset=utf-8']);
        });

        $this->artisan('pia-ndc:void-ticket', [
            'booking' => $booking->id,
            '--connection' => $connection->id,
            '--execute-void' => true,
            '--confirm' => 'VOID_PIA_NDC_TICKET',
        ])->assertSuccessful();

        $booking->refresh();
        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', PiaNdcOperationAuditRecorder::ACTION_VOID_TICKET)
            ->latest('id')
            ->first();
        $this->assertNotNull($attempt);
        $this->assertSame('success', $attempt->status);
        $this->assertIsArray($booking->meta[PiaNdcOperationAuditRecorder::META_VOID_TICKET] ?? null);
        $this->assertSame('voided', $booking->meta[PiaNdcOperationAuditRecorder::META_VOID_TICKET]['void_status'] ?? null);
        $this->assertSame('doVoidTicket', $booking->meta[PiaNdcOperationAuditRecorder::META_VOID_TICKET]['operation'] ?? null);
        $this->assertSame('option_pnr_after_void', $booking->supplier_booking_status);
    }

    public function test_void_default_is_dry_run_and_no_supplier_call(): void
    {
        Http::fake();
        [$booking] = $this->piaBooking([
            'ticket_numbers' => ['1761234567890'],
            'ticketing_status' => 'ticketed',
        ]);

        $this->artisan('pia-ndc:void-ticket', ['booking' => $booking->id])
            ->expectsOutputToContain('dry_run=true')
            ->expectsOutputToContain('supplier_called=false')
            ->expectsOutputToContain('operation=doVoidTicket')
            ->expectsOutputToContain('real_ticket_numbers_present=true')
            ->expectsOutputToContain('request_built=true')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_void_execute_requires_confirm(): void
    {
        [$booking] = $this->piaBooking([
            'ticket_numbers' => ['1761234567890'],
        ]);

        $this->artisan('pia-ndc:void-ticket', [
            'booking' => $booking->id,
            '--execute-void' => true,
        ])
            ->expectsOutputToContain('VOID_PIA_NDC_TICKET')
            ->assertFailed();
    }

    public function test_void_blocks_without_real_ticket_numbers(): void
    {
        Http::fake();
        [$booking] = $this->piaBooking();

        $this->artisan('pia-ndc:void-ticket', ['booking' => $booking->id])
            ->expectsOutputToContain('real_ticket_numbers_present=false')
            ->expectsOutputToContain('request_built=false')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_void_blocks_duplicate_void(): void
    {
        Http::fake();
        [$booking] = $this->piaBooking([
            'void_status' => 'voided',
            'ticket_numbers' => ['1761234567890'],
        ]);

        $this->artisan('pia-ndc:void-ticket', ['booking' => $booking->id])
            ->expectsOutputToContain('duplicate_void_guard')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_dry_run_does_not_persist_preview_ticketing_or_void_status(): void
    {
        Http::fake();
        $this->platformAdmin();

        [$previewBooking] = $this->piaBooking();
        $previewMeta = $previewBooking->meta;
        $this->artisan('pia-ndc:test-ticket-preview', ['booking' => $previewBooking->id])->assertSuccessful();
        $previewBooking->refresh();
        $this->assertSame($previewMeta, $previewBooking->meta);

        [$ticketingBooking] = $this->piaBooking([
            'ticket_preview' => ['amount' => 100.0, 'currency' => 'PKR'],
        ]);
        $ticketingMeta = $ticketingBooking->meta;
        $this->artisan('pia-ndc:test-ticketing', ['booking' => $ticketingBooking->id])->assertSuccessful();
        $ticketingBooking->refresh();
        $this->assertSame($ticketingMeta, $ticketingBooking->meta);

        [$voidBooking] = $this->piaBooking([
            'ticket_numbers' => ['1761234567890'],
            'ticketing_status' => 'ticketed',
        ]);
        $voidMeta = $voidBooking->meta;
        $this->artisan('pia-ndc:void-ticket', ['booking' => $voidBooking->id])->assertSuccessful();
        $voidBooking->refresh();
        $this->assertSame($voidMeta, $voidBooking->meta);

        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $contextOverrides
     * @return array{0: Booking, 1: SupplierConnection}
     */
    private function piaBooking(array $contextOverrides = []): array
    {
        $connection = SupplierConnection::factory()->create([
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
                'mco_invoice_number' => 'MCO-TEST-001',
                'payment_type' => 'MCO',
            ],
            'is_active' => true,
        ]);

        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::PiaNdc->value,
            'supplier_reference' => '7UU0J3',
            'meta' => [
                'supplier_provider' => SupplierProvider::PiaNdc->value,
                'supplier_connection_id' => $connection->id,
                'pia_ndc_context' => array_merge([
                    'order_id' => '7UU0J3',
                    'owner_code' => 'PK',
                ], $contextOverrides),
            ],
        ]);

        SupplierBooking::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::PiaNdc->value,
            'supplier_reference' => '7UU0J3',
            'pnr' => '7UU0J3',
            'status' => 'confirmed',
            'raw_summary' => ['seeded' => true],
            'created_at_supplier' => now(),
        ]);

        return [$booking, $connection];
    }

    private function retrieveXmlWithPaymentLimit(string $paymentLimit): string
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderRetrieve_OW_res.xml')) ?: '';

        return str_replace('2025-05-27T15:17:52+05:00', $paymentLimit, $xml);
    }
}
