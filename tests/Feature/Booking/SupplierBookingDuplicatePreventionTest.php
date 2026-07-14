<?php

namespace Tests\Feature\Booking;

use App\Data\SupplierBookingResultData;
use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\PlatformModuleSetting;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Booking\BookingProviderRouter;
use App\Services\Platform\PlatformModuleSettingsService;
use App\Services\Suppliers\BookingAdapters\DuffelSupplierBookingAdapter;
use App\Services\Suppliers\SupplierBookingService;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class SupplierBookingDuplicatePreventionTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_booking_with_existing_supplier_reference_cannot_create_second_supplier_booking(): void
    {
        Http::fake();
        $this->mock(DuffelSupplierBookingAdapter::class, function ($mock): void {
            $mock->shouldReceive('createSupplierBooking')->never();
        });

        $booking = $this->eligibleDuffelBooking();
        $booking->update(['supplier_reference' => 'REF-DUP', 'pnr' => null]);
        $admin = $this->platformAdmin();

        $result = app(BookingProviderRouter::class)->createSupplierBooking($booking->fresh(), $admin);

        $this->assertTrue($result->success);
        $this->assertSame('REF-DUP', $result->supplier_reference);
        $this->assertDatabaseMissing('supplier_bookings', ['booking_id' => $booking->id]);
        Http::assertNothingSent();
    }

    public function test_successful_supplier_attempt_blocks_automatic_duplicate_create(): void
    {
        Http::fake();
        $this->mock(DuffelSupplierBookingAdapter::class, function ($mock): void {
            $mock->shouldReceive('createSupplierBooking')->never();
        });

        $booking = $this->eligibleDuffelBooking();
        $admin = $this->platformAdmin();
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Duffel->value,
            'action' => 'create_pnr',
            'status' => 'success',
            'supplier_reference' => 'ord_existing',
            'attempted_by' => $admin->id,
            'attempted_at' => now(),
            'completed_at' => now(),
            'safe_summary' => ['source' => 'admin', 'mode' => 'test'],
        ]);

        $result = app(BookingProviderRouter::class)->createSupplierBooking($booking->fresh(), $admin);

        $this->assertTrue($result->success);
        $this->assertSame('ord_existing', $result->supplier_reference);
        Http::assertNothingSent();
    }

    public function test_processing_attempt_returns_already_processing_without_adapter_call(): void
    {
        Http::fake();
        $this->mock(DuffelSupplierBookingAdapter::class, function ($mock): void {
            $mock->shouldReceive('createSupplierBooking')->never();
        });

        $booking = $this->eligibleDuffelBooking();
        $admin = $this->platformAdmin();
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Duffel->value,
            'action' => 'create_pnr',
            'status' => 'processing',
            'attempted_by' => $admin->id,
            'attempted_at' => now(),
            'safe_summary' => ['source' => 'admin'],
        ]);

        $result = app(BookingProviderRouter::class)->createSupplierBooking($booking->fresh(), $admin);

        $this->assertFalse($result->success);
        $this->assertSame('blocked', $result->status);
        $this->assertSame('supplier_booking_already_processing', $result->error_code);
        Http::assertNothingSent();
    }

    public function test_manual_pnr_attach_does_not_overwrite_existing_supplier_reference(): void
    {
        $booking = $this->eligibleDuffelBooking();
        $booking->update(['pnr' => 'EXIST1', 'supplier_reference' => 'REF1']);
        $admin = $this->platformAdmin();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already exists');

        app(SupplierBookingService::class)->markManualPnr($booking->fresh(), $admin, 'NEWPNR');
    }

    public function test_manual_pnr_attach_records_manual_audit_and_meta(): void
    {
        $booking = $this->eligibleDuffelBooking();
        $admin = $this->platformAdmin();

        app(SupplierBookingService::class)->markManualPnr($booking->fresh(), $admin, 'abc12', 'SUP-REF', 'ops note');

        $fresh = $booking->fresh();
        $this->assertSame('ABC12', $fresh->pnr);
        $this->assertSame('SUP-REF', $fresh->supplier_reference);
        $this->assertSame('manual', $fresh->meta['manual_pnr']['source'] ?? null);
        $this->assertSame($admin->id, $fresh->meta['manual_pnr']['entered_by'] ?? null);

        $this->assertDatabaseHas('supplier_booking_attempts', [
            'booking_id' => $booking->id,
            'action' => 'mark_manual_pnr',
            'status' => 'success',
        ]);

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->latest('id')->first();
        $summary = is_array($attempt?->safe_summary) ? $attempt->safe_summary : [];
        $this->assertSame('manual', $summary['source'] ?? null);
        $this->assertArrayNotHasKey('note', $summary);
        $this->assertNull($attempt?->request_payload);
        $this->assertNull($attempt?->response_payload);
    }

    public function test_manual_pnr_note_persisted_in_meta_and_audit(): void
    {
        $booking = $this->eligibleDuffelBooking();
        $admin = $this->platformAdmin();

        app(SupplierBookingService::class)->markManualPnr($booking->fresh(), $admin, 'abc12', 'SUP-REF', 'entered via ops desk');

        $fresh = $booking->fresh();
        $this->assertSame('entered via ops desk', $fresh->meta['manual_pnr']['note'] ?? null);

        $audit = AuditLog::query()
            ->where('auditable_type', Booking::class)
            ->where('auditable_id', $booking->id)
            ->where('action', 'booking.manual_pnr_marked')
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame('entered via ops desk', $audit->properties['new_values']['note'] ?? null);
    }

    public function test_manual_pnr_does_not_change_payment_or_ticketing_status(): void
    {
        $booking = $this->eligibleDuffelBooking();
        $booking->update([
            'payment_status' => 'unpaid',
            'ticketing_status' => 'not_started',
            'supplier_booking_status' => 'manual_review',
        ]);
        $admin = $this->platformAdmin();

        Http::fake();
        app(SupplierBookingService::class)->markManualPnr($booking->fresh(), $admin, 'TESTPN', 'SUP-1', 'qa');

        $fresh = $booking->fresh();
        $this->assertSame('unpaid', $fresh->payment_status);
        $this->assertSame('not_started', $fresh->ticketing_status);
        $this->assertSame('TESTPN', $fresh->pnr);
        $this->assertSame('pending_ticketing', $fresh->supplier_booking_status);
        Http::assertNothingSent();
    }

    public function test_customer_cannot_post_admin_manual_pnr_route(): void
    {
        $booking = $this->eligibleDuffelBooking();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();

        $this->actingAs($customer)
            ->post(route('admin.bookings.manual-pnr', $booking), ['pnr' => 'HACK01'])
            ->assertForbidden();

        $this->assertNull($booking->fresh()->pnr);
    }

    public function test_staff_with_permission_can_post_manual_pnr_route(): void
    {
        $booking = $this->eligibleDuffelBooking();
        $staff = $this->staffWithPermissions([StaffPermission::BookingsView, StaffPermission::BookingsUpdateStatus]);

        $this->actingAs($staff)
            ->post(route('staff.bookings.manual-pnr', $booking), [
                'pnr' => 'STAFF1',
                'supplier_reference' => 'REF-STAFF',
                'note' => 'route test',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'manual-pnr-marked');

        $fresh = $booking->fresh();
        $this->assertSame('STAFF1', $fresh->pnr);
        $this->assertSame('REF-STAFF', $fresh->supplier_reference);
    }

    public function test_supplier_booking_off_blocks_staff_supplier_post_at_route(): void
    {
        $this->planModuleOff('supplier_booking');
        $booking = $this->eligibleDuffelBooking();
        $staff = $this->staffWithPermissions([StaffPermission::BookingsView, StaffPermission::BookingsUpdateStatus]);

        $this->actingAs($staff)
            ->post(route('staff.bookings.supplier-booking', $booking))
            ->assertForbidden();
    }

    public function test_provider_module_off_blocks_supplier_booking_at_service_layer(): void
    {
        $this->planModuleOff('duffel_supplier');
        Http::fake();
        $this->mock(DuffelSupplierBookingAdapter::class, function ($mock): void {
            $mock->shouldReceive('createSupplierBooking')->never();
        });

        $booking = $this->eligibleDuffelBooking();
        $result = app(BookingProviderRouter::class)->createSupplierBooking($booking, $this->platformAdmin());

        $this->assertFalse($result->success);
        $this->assertSame('platform_module_disabled', $result->error_code);
        Http::assertNothingSent();
    }

    public function test_retry_blocked_when_existing_pnr_on_booking(): void
    {
        Http::fake();
        $this->mock(DuffelSupplierBookingAdapter::class, function ($mock): void {
            $mock->shouldReceive('createSupplierBooking')->never();
        });

        $booking = $this->eligibleDuffelBooking();
        $booking->update(['pnr' => 'PNR99', 'supplier_reference' => 'REF99']);
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Duffel->value,
            'action' => 'create_pnr',
            'status' => 'failed',
            'error_code' => 'transport_timeout',
            'attempted_by' => $this->platformAdmin()->id,
            'attempted_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(10),
        ]);

        $result = app(BookingProviderRouter::class)->createSupplierBooking(
            $booking->fresh(),
            $this->platformAdmin(),
            adminOverride: false,
            allowControlledStaffPnr: true,
            explicitRetry: true,
        );

        $this->assertTrue($result->success);
        $this->assertSame('PNR99', $result->pnr);
        Http::assertNothingSent();
    }

    public function test_retry_allowed_after_failed_retryable_attempt_without_pnr(): void
    {
        Http::fake();
        $this->mock(DuffelSupplierBookingAdapter::class, function ($mock): void {
            $mock->shouldReceive('createSupplierBooking')->once()->andReturn(new SupplierBookingResultData(
                success: true,
                status: 'success',
                provider: SupplierProvider::Duffel->value,
                supplier_reference: 'ord_retry',
                pnr: 'PNRRET',
                safe_summary: ['mode' => 'test'],
            ));
        });

        $admin = $this->platformAdmin();
        $conn = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();
        $conn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'environment' => SupplierEnvironment::Sandbox,
        ]);

        $booking = Booking::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => 'duffel',
            'meta' => [
                'validated_offer_snapshot' => ['offer_id' => 'offer-retry-9d3'],
                'supplier_provider' => 'duffel',
                'supplier_connection_id' => $conn->id,
            ],
        ]);

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Duffel->value,
            'action' => 'create_pnr',
            'status' => 'failed',
            'error_code' => 'transport_timeout',
            'attempted_by' => $admin->id,
            'attempted_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(10),
        ]);

        $result = app(BookingProviderRouter::class)->createSupplierBooking(
            $booking->fresh(),
            $admin,
            allowControlledStaffPnr: true,
        );

        $this->assertTrue($result->success, (string) ($result->error_message ?? $result->error_code ?? ''));
        $this->assertSame('PNRRET', $result->pnr);
        $this->assertDatabaseHas('supplier_bookings', [
            'booking_id' => $booking->id,
            'pnr' => 'PNRRET',
        ]);
    }

    public function test_non_retryable_sabre_failure_blocks_automatic_retry(): void
    {
        Http::fake();
        $booking = $this->eligibleSabreBooking();
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'failed',
            'error_code' => 'sabre_passenger_records_stale_shop_segment',
            'safe_summary' => ['stale_segment_route' => 'LHE-DXB'],
            'attempted_by' => $this->platformAdmin()->id,
            'attempted_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(10),
        ]);

        $result = app(BookingProviderRouter::class)->createSupplierBooking($booking->fresh(), $this->platformAdmin());

        $this->assertFalse($result->success);
        $this->assertSame('supplier_booking_retry_not_allowed', $result->error_code);
        Http::assertNothingSent();
    }

    public function test_attempt_safe_summary_never_stores_passenger_pii_keys(): void
    {
        $booking = $this->eligibleDuffelBooking();
        $admin = $this->platformAdmin();
        $booking->update(['pnr' => 'BLOCK', 'supplier_reference' => 'R1']);

        app(BookingProviderRouter::class)->createSupplierBooking($booking->fresh(), $admin);

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->latest('id')->first();
        $this->assertNotNull($attempt);
        $encoded = json_encode([
            'safe_summary' => $attempt->safe_summary,
            'request_payload' => $attempt->request_payload,
            'response_payload' => $attempt->response_payload,
        ]);
        $this->assertIsString($encoded);
        foreach (['first_name', 'last_name', 'passenger_name', 'email', 'phone', 'authorization', 'client_secret', 'token'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, strtolower($encoded));
        }
    }

    protected function eligibleDuffelBooking(): Booking
    {
        $admin = $this->platformAdmin();
        $agencyId = (int) $admin->current_agency_id;
        $connection = SupplierConnection::query()
            ->where('agency_id', $agencyId)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();
        $connection->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'environment' => SupplierEnvironment::Sandbox,
        ]);

        return Booking::factory()->create([
            'agency_id' => $agencyId,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Duffel->value,
            'source_channel' => 'agent_portal',
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
                'supplier_connection_id' => $connection->id,
                'validated_offer_snapshot' => ['offer_id' => 'duffel-dup-9d3'],
            ],
        ]);
    }

    protected function eligibleSabreBooking(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $connection = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $connection->update(['is_active' => true, 'status' => SupplierConnectionStatus::Active]);

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $connection->id,
                'validated_offer_snapshot' => ['offer_id' => 'sabre-dup-9d3'],
                'normalized_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'offer_id' => 'sabre-dup-9d3',
                    'segments' => [[
                        'origin' => 'LHE',
                        'destination' => 'DXB',
                        'departure_at' => '2026-06-01T08:00:00Z',
                        'arrival_at' => '2026-06-01T14:00:00Z',
                    ]],
                ],
            ],
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function staffWithPermissions(array $permissions): User
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill(['meta' => ['staff_permissions' => $permissions]])->save();

        return $staff->fresh();
    }

    private function planModuleOff(string $key): void
    {
        PlatformModuleSetting::query()->create([
            'module_key' => $key,
            'enabled' => false,
        ]);
        app(PlatformModuleSettingsService::class)->forgetCache();
    }
}
