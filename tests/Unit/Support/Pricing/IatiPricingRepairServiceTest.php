<?php

namespace Tests\Unit\Support\Pricing;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingHoldSession;
use App\Models\BookingPassenger;
use App\Support\Pricing\IatiPricingRepairService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IatiPricingRepairServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_booking_59_style_audit_detects_double_conversion_without_passenger_pricing_total(): void
    {
        $booking = $this->booking59StyleInflated();
        $audit = app(IatiPricingRepairService::class)->audit($booking);

        $this->assertTrue($audit['detected_double_conversion']);
        $this->assertTrue($audit['safe_repair_available']);
        $this->assertSame([], $audit['repair_blockers']);
        $this->assertEqualsWithDelta(119090.0, (float) $audit['expected_total_pkr'], 0.01);
        $this->assertNull($audit['passenger_pricing_total']);
    }

    #[Test]
    public function test_repair_dry_run_reports_changes_only(): void
    {
        $booking = $this->booking59StyleInflated();
        $result = app(IatiPricingRepairService::class)->repair($booking, false);

        $this->assertFalse($result['applied']);
        $this->assertSame([], $result['blockers']);
        $this->assertEqualsWithDelta(119090.0, (float) ($result['changes']['bookings.selected_fare_total'] ?? 0), 0.01);
        $booking->refresh();
        $this->assertEqualsWithDelta(33109533.63, (float) $booking->selected_fare_total, 0.01);
    }

    #[Test]
    public function test_repair_apply_fixes_unpaid_booking_without_supplier_order(): void
    {
        $booking = $this->booking59StyleInflated();
        $result = app(IatiPricingRepairService::class)->repair($booking, true);

        $this->assertTrue($result['applied']);
        $booking->refresh()->load('fareBreakdown');
        $this->assertEqualsWithDelta(119090.0, (float) $booking->selected_fare_total, 0.01);
        $this->assertEqualsWithDelta(119090.0, (float) $booking->revalidated_fare_total, 0.01);
        $this->assertSame('PKR', $booking->fareBreakdown?->currency);
        $this->assertEqualsWithDelta(119090.0, (float) $booking->fareBreakdown?->total, 0.01);
        $this->assertEqualsWithDelta(101290.0, (float) $booking->fareBreakdown?->base_fare, 1.0);
        $this->assertEqualsWithDelta(17300.0, (float) $booking->fareBreakdown?->taxes, 1.0);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertSame('PKR', $meta['pricing_snapshot']['supplier_currency'] ?? null);
        $this->assertSame('same_currency', $meta['pricing_snapshot']['conversion_status'] ?? null);
        $this->assertEqualsWithDelta(119090.0, (float) ($meta['pricing_snapshot']['final_total'] ?? 0), 0.01);
        $this->assertNull($booking->pnr);
        $this->assertNull($booking->supplier_reference);
    }

    #[Test]
    public function test_repair_updates_linked_hold_session_but_not_unrelated_orphan_row(): void
    {
        $booking = $this->booking59StyleInflated();
        $linkedHold = BookingHoldSession::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'search_id' => 'search-59',
            'offer_id' => 'offer-59',
            'supplier_provider' => SupplierProvider::Iati->value,
            'validated_total_amount' => 33109533.63,
            'validated_total_currency' => 'USD',
            'converted_total_pkr' => 33109533.63,
            'hold_status' => 'pending',
            'requires_instant_payment' => true,
            'expires_at' => now()->addHour(),
        ]);
        $booking->update(['hold_session_id' => $linkedHold->id]);

        $orphanHold = BookingHoldSession::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => null,
            'search_id' => 'search-59',
            'offer_id' => 'offer-59',
            'supplier_provider' => SupplierProvider::Iati->value,
            'validated_total_amount' => 33109533.63,
            'validated_total_currency' => 'USD',
            'converted_total_pkr' => 33109533.63,
            'hold_status' => 'pending',
            'requires_instant_payment' => true,
            'expires_at' => now()->addHour(),
        ]);

        $audit = app(IatiPricingRepairService::class)->audit($booking->fresh());
        $this->assertContains($linkedHold->id, $audit['repairable_hold_session_ids']);
        $this->assertContains($orphanHold->id, $audit['report_only_hold_session_ids']);

        app(IatiPricingRepairService::class)->repair($booking->fresh(), true);

        $linkedHold->refresh();
        $orphanHold->refresh();
        $this->assertEqualsWithDelta(119090.0, (float) $linkedHold->validated_total_amount, 0.01);
        $this->assertEqualsWithDelta(33109533.63, (float) $orphanHold->validated_total_amount, 0.01);
    }

    #[Test]
    public function test_residual_audit_detects_stale_hold_and_orphan_candidate_after_pricing_repair(): void
    {
        [$booking, $staleHold, $orphanHold] = $this->booking59StylePostPricingRepair();

        $audit = app(IatiPricingRepairService::class)->audit($booking);

        $this->assertFalse($audit['detected_double_conversion']);
        $this->assertFalse($audit['safe_repair_available']);
        $this->assertTrue($audit['residual_repair_available']);
        $this->assertSame($staleHold->id, $audit['linked_hold_session_id']);
        $this->assertFalse($audit['linked_hold_session_matches_booking']);
        $this->assertSame([$orphanHold->id], $audit['candidate_orphan_hold_session_ids']);
        $this->assertTrue($audit['hold_session_relink_available']);
        $this->assertSame($staleHold->id, $audit['stale_hold_session_id']);
        $this->assertTrue($audit['nested_breakdown_repair_available']);
        $this->assertArrayHasKey('bookings.hold_session_id', $audit['planned_residual_changes']);
        $this->assertSame($orphanHold->id, $audit['planned_residual_changes']['bookings.hold_session_id']);
    }

    #[Test]
    public function test_residual_dry_run_reports_hold_relink_and_nested_json_repairs(): void
    {
        [$booking] = $this->booking59StylePostPricingRepair();

        $result = app(IatiPricingRepairService::class)->repair($booking, false);

        $this->assertFalse($result['applied']);
        $this->assertSame([], $result['blockers']);
        $this->assertArrayHasKey('bookings.hold_session_id', $result['planned_residual_changes']);
        $this->assertArrayHasKey('booking_fare_breakdowns.breakdown', $result['planned_residual_changes']);
        $booking->refresh();
        $this->assertEqualsWithDelta(119090.0, (float) $booking->selected_fare_total, 0.01);
    }

    #[Test]
    public function test_residual_apply_relinks_hold_session_and_fixes_nested_breakdown(): void
    {
        [$booking, $staleHold, $orphanHold] = $this->booking59StylePostPricingRepair();

        $result = app(IatiPricingRepairService::class)->repair($booking, true);

        $this->assertTrue($result['applied']);
        $booking->refresh()->load('fareBreakdown');
        $staleHold->refresh();
        $orphanHold->refresh();

        $this->assertSame($orphanHold->id, (int) $booking->hold_session_id);
        $this->assertSame((int) $booking->id, (int) $orphanHold->booking_id);
        $this->assertNull($staleHold->booking_id);

        $this->assertEqualsWithDelta(119090.0, (float) $orphanHold->validated_total_amount, 0.01);
        $this->assertSame('PKR', $orphanHold->validated_total_currency);
        $this->assertEqualsWithDelta(119090.0, (float) $orphanHold->converted_total_pkr, 0.01);

        $markup = is_array($orphanHold->markup_snapshot) ? $orphanHold->markup_snapshot : [];
        $this->assertEqualsWithDelta(119090.0, (float) ($markup['supplier_total'] ?? 0), 0.01);
        $this->assertSame('PKR', $markup['supplier_currency'] ?? null);
        $this->assertSame('same_currency', $markup['conversion_status'] ?? null);
        $this->assertSame(1, (int) ($markup['fx_rate'] ?? 0));

        $validated = is_array($orphanHold->validated_offer_snapshot) ? $orphanHold->validated_offer_snapshot : [];
        $this->assertEqualsWithDelta(119090.0, (float) ($validated['total'] ?? 0), 0.01);
        $this->assertEqualsWithDelta(101290.0, (float) data_get($validated, 'fare_breakdown.base_fare'), 1.0);
        $this->assertEqualsWithDelta(17300.0, (float) data_get($validated, 'fare_breakdown.taxes'), 1.0);
        $this->assertEqualsWithDelta(119090.0, (float) data_get($validated, 'pricing_components.final_total'), 0.01);

        $rows = is_array($booking->fareBreakdown?->breakdown) ? $booking->fareBreakdown->breakdown : [];
        $baseRow = collect($rows)->firstWhere('label', 'Base fare');
        $taxRow = collect($rows)->firstWhere('label', 'Taxes & surcharges');
        $this->assertEqualsWithDelta(101290.0, (float) ($baseRow['amount'] ?? 0), 1.0);
        $this->assertEqualsWithDelta(17300.0, (float) ($taxRow['amount'] ?? 0), 1.0);

        $this->assertNull($booking->pnr);
        $this->assertNull($booking->supplier_reference);
        $this->assertNull($orphanHold->supplier_order_id);
        $this->assertNull($orphanHold->supplier_order_reference);
    }

    #[Test]
    public function test_residual_does_not_relink_when_multiple_orphan_candidates_exist(): void
    {
        [$booking, , $orphanHold] = $this->booking59StylePostPricingRepair();

        BookingHoldSession::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => null,
            'search_id' => '81628407-b264-46e6-968a-d5501de1a2b0',
            'offer_id' => 'iati_910e1a06a66630ca',
            'supplier_provider' => SupplierProvider::Iati->value,
            'validated_total_amount' => 119090,
            'validated_total_currency' => 'PKR',
            'converted_total_pkr' => 119090,
            'hold_status' => 'pending',
            'requires_instant_payment' => true,
            'expires_at' => now()->addHour(),
        ]);

        $audit = app(IatiPricingRepairService::class)->audit($booking->fresh());
        $this->assertFalse($audit['hold_session_relink_available']);
        $this->assertContains('multiple_orphan_candidates', $audit['residual_repair_blockers']);

        app(IatiPricingRepairService::class)->repair($booking->fresh(), true);
        $booking->refresh();
        $this->assertNotSame($orphanHold->id, (int) $booking->hold_session_id);
    }

    #[Test]
    public function test_residual_does_not_relink_when_candidate_has_supplier_order_id(): void
    {
        [$booking, , $orphanHold] = $this->booking59StylePostPricingRepair();
        $orphanHold->update(['supplier_order_id' => 'IATI-ORDER-1']);

        $audit = app(IatiPricingRepairService::class)->audit($booking->fresh());
        $this->assertFalse($audit['hold_session_relink_available']);
        $this->assertContains('candidate_supplier_order_id_present', $audit['residual_repair_blockers']);
    }

    #[Test]
    public function test_residual_does_not_relink_when_candidate_has_supplier_order_reference(): void
    {
        [$booking, , $orphanHold] = $this->booking59StylePostPricingRepair();
        $orphanHold->update(['supplier_order_reference' => 'ABC123']);

        $audit = app(IatiPricingRepairService::class)->audit($booking->fresh());
        $this->assertFalse($audit['hold_session_relink_available']);
        $this->assertContains('candidate_supplier_order_reference_present', $audit['residual_repair_blockers']);
    }

    #[Test]
    public function test_non_iati_booking_is_unchanged_by_residual_repair(): void
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Duffel->value,
            'payment_status' => 'unpaid',
            'meta' => ['supplier_provider' => SupplierProvider::Duffel->value],
        ]);

        $audit = app(IatiPricingRepairService::class)->audit($booking);
        $this->assertFalse($audit['residual_repair_available']);
        $this->assertContains('not_iati', $audit['residual_repair_blockers']);

        $result = app(IatiPricingRepairService::class)->repair($booking, true);
        $this->assertFalse($result['applied']);
    }

    #[Test]
    public function test_booking_60_style_null_hold_session_links_single_orphan_via_residual_repair(): void
    {
        [$booking, $orphanHold] = $this->booking60StyleCleanPricingOrphanHold();

        $audit = app(IatiPricingRepairService::class)->audit($booking);

        $this->assertFalse($audit['detected_double_conversion']);
        $this->assertFalse($audit['safe_repair_available']);
        $this->assertTrue($audit['residual_repair_available']);
        $this->assertNull($audit['linked_hold_session_id']);
        $this->assertFalse($audit['linked_hold_session_matches_booking']);
        $this->assertSame([$orphanHold->id], $audit['candidate_orphan_hold_session_ids']);
        $this->assertTrue($audit['hold_session_link_available']);
        $this->assertTrue($audit['hold_session_relink_available']);
        $this->assertSame($orphanHold->id, $audit['planned_residual_changes']['bookings.hold_session_id']);
        $this->assertSame(
            (int) $booking->id,
            $audit['planned_residual_changes']['booking_hold_sessions.'.$orphanHold->id.'.booking_id'],
        );
    }

    #[Test]
    public function test_booking_60_style_orphan_link_dry_run_does_not_persist(): void
    {
        [$booking, $orphanHold] = $this->booking60StyleCleanPricingOrphanHold();

        $result = app(IatiPricingRepairService::class)->repair($booking, false);

        $this->assertFalse($result['applied']);
        $this->assertSame([], $result['blockers']);
        $booking->refresh();
        $orphanHold->refresh();
        $this->assertNull($booking->hold_session_id);
        $this->assertNull($orphanHold->booking_id);
    }

    #[Test]
    public function test_booking_60_style_orphan_link_apply_preserves_totals_and_supplier_fields(): void
    {
        [$booking, $orphanHold] = $this->booking60StyleCleanPricingOrphanHold();

        $result = app(IatiPricingRepairService::class)->repair($booking, true);

        $this->assertTrue($result['applied']);
        $booking->refresh();
        $orphanHold->refresh();

        $this->assertSame($orphanHold->id, (int) $booking->hold_session_id);
        $this->assertSame((int) $booking->id, (int) $orphanHold->booking_id);
        $this->assertEqualsWithDelta(89717.0, (float) $booking->selected_fare_total, 0.01);
        $this->assertEqualsWithDelta(89717.0, (float) $orphanHold->validated_total_amount, 0.01);
        $this->assertSame('PKR', $orphanHold->validated_total_currency);
        $this->assertNull($orphanHold->supplier_order_id);
        $this->assertNull($orphanHold->supplier_order_reference);
        $this->assertNull($booking->pnr);
        $this->assertNull($booking->supplier_reference);
    }

    /**
     * @return array{0: Booking, 1: BookingHoldSession}
     */
    protected function booking60StyleCleanPricingOrphanHold(): array
    {
        $agency = Agency::factory()->create();
        $searchId = '05e1da25-5925-40bc-8f66-58ec552a9fca';
        $offerId = 'iati_7e96ed26e2213b49';

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Iati->value,
            'payment_status' => 'unpaid',
            'hold_session_id' => null,
            'selected_fare_total' => 89717,
            'revalidated_fare_total' => 89717,
            'meta' => [
                'supplier_provider' => SupplierProvider::Iati->value,
                'checkout_search_id' => $searchId,
                'checkout_offer_id' => $offerId,
                'iati_context' => [
                    'departure_fare_key' => 'dep-pk-60',
                    'fare_detail_key' => 'fare-detail-pk-60',
                ],
                'pricing_snapshot' => [
                    'base_fare' => 54400,
                    'taxes' => 34817,
                    'supplier_total' => 89717,
                    'supplier_total_source' => 89717.0,
                    'supplier_currency' => 'PKR',
                    'pricing_currency' => 'PKR',
                    'conversion_status' => 'same_currency',
                    'fx_rate' => 1,
                    'final_total' => 89717,
                ],
            ],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 54400,
            'taxes' => 34817,
            'total' => 89717,
            'currency' => 'PKR',
        ]);

        $orphanHold = BookingHoldSession::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => null,
            'search_id' => $searchId,
            'offer_id' => $offerId,
            'supplier_provider' => SupplierProvider::Iati->value,
            'validated_total_amount' => 89717,
            'validated_total_currency' => 'PKR',
            'converted_total_pkr' => 89717,
            'hold_status' => 'not_supported',
            'requires_instant_payment' => true,
            'expires_at' => now()->addHour(),
        ]);

        return [$booking->fresh(['fareBreakdown']), $orphanHold];
    }

    protected function booking59StyleInflated(): Booking
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Iati->value,
            'payment_status' => 'unpaid',
            'selected_fare_total' => 33109533.63,
            'revalidated_fare_total' => 33109533.63,
            'meta' => [
                'supplier_provider' => SupplierProvider::Iati->value,
                'checkout_search_id' => 'search-59',
                'checkout_offer_id' => 'offer-59',
                'selected_fare_family_option' => [
                    'displayed_price' => 119090,
                    'displayed_currency' => 'PKR',
                    'name' => 'Fare 2',
                ],
                'pricing_snapshot' => [
                    'base_fare' => 28160757.93,
                    'taxes' => 4809765.15,
                    'supplier_total' => 33109533.63,
                    'supplier_total_source' => 119090.0,
                    'supplier_currency' => 'USD',
                    'pricing_currency' => 'PKR',
                    'conversion_status' => 'converted',
                    'fx_rate' => 278.021107,
                    'final_total' => 33109533.63,
                ],
            ],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 28160757.93,
            'taxes' => 4809765.15,
            'total' => 33109533.63,
            'currency' => 'USD',
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'pax@example.com',
            'phone' => '3001234567',
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'date_of_birth' => '1990-01-01',
            'passport_number' => 'AB1234567',
            'passport_expiry_date' => '2030-01-01',
            'nationality' => 'PK',
            'passenger_type' => 'adult',
        ]);

        return $booking->fresh(['fareBreakdown']);
    }

    /**
     * @return array{0: Booking, 1: BookingHoldSession, 2: BookingHoldSession}
     */
    protected function booking59StylePostPricingRepair(): array
    {
        $agency = Agency::factory()->create();
        $searchId = '81628407-b264-46e6-968a-d5501de1a2b0';
        $offerId = 'iati_910e1a06a66630ca';

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Iati->value,
            'payment_status' => 'unpaid',
            'selected_fare_total' => 119090,
            'revalidated_fare_total' => 119090,
            'meta' => [
                'supplier_provider' => SupplierProvider::Iati->value,
                'checkout_search_id' => $searchId,
                'checkout_offer_id' => $offerId,
                'flight_offer_snapshot' => [
                    'airline_code' => 'PF',
                    'origin' => 'LHE',
                    'destination' => 'JED',
                ],
                'selected_fare_family_option' => [
                    'displayed_price' => 119090,
                    'displayed_currency' => 'PKR',
                    'name' => 'Fare 2',
                ],
                'pricing_snapshot' => [
                    'base_fare' => 101290,
                    'taxes' => 17300,
                    'supplier_total' => 119090,
                    'supplier_total_source' => 119090.0,
                    'supplier_currency' => 'PKR',
                    'pricing_currency' => 'PKR',
                    'conversion_status' => 'same_currency',
                    'fx_rate' => 1,
                    'final_total' => 119090,
                ],
            ],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 101290,
            'taxes' => 17300,
            'total' => 119090,
            'currency' => 'PKR',
            'breakdown' => [
                ['label' => 'Base fare', 'amount' => 28160757.93],
                ['label' => 'Taxes & surcharges', 'amount' => 4809765.15],
                ['label' => 'Admin markup', 'amount' => 0],
            ],
        ]);

        $staleHold = BookingHoldSession::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'search_id' => '76d8a1b3-8266-4a99-81f7-a594cfa0ffe8',
            'offer_id' => 'iati_e529b95973f6a318',
            'supplier_provider' => SupplierProvider::Iati->value,
            'validated_total_amount' => 33109533.63,
            'validated_total_currency' => 'USD',
            'converted_total_pkr' => 33109533.63,
            'hold_status' => 'pending',
            'requires_instant_payment' => true,
            'expires_at' => now()->addHour(),
        ]);

        $orphanHold = BookingHoldSession::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => null,
            'search_id' => $searchId,
            'offer_id' => $offerId,
            'supplier_provider' => SupplierProvider::Iati->value,
            'validated_total_amount' => 33109533.63,
            'validated_total_currency' => 'USD',
            'converted_total_pkr' => 33109533.63,
            'hold_status' => 'pending',
            'requires_instant_payment' => true,
            'expires_at' => now()->addHour(),
        ]);

        $booking->update(['hold_session_id' => $staleHold->id]);

        return [$booking->fresh(['fareBreakdown', 'holdSession']), $staleHold, $orphanHold];
    }
}
