<?php

namespace Tests\Feature;

use App\Console\Commands\SupplierValidationStrategyDigestCommand;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Support\Suppliers\SupplierValidationActionCode;
use App\Support\Suppliers\SupplierValidationStrategyRegistry;
use App\Support\Suppliers\SupplierValidationStrategySelector;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierValidationStrategyRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_registry_declares_all_validation_actions(): void
    {
        $registry = app(SupplierValidationStrategyRegistry::class);
        foreach (SupplierValidationActionCode::ALL as $action) {
            $this->assertNotSame([], $registry->supportedCodesForAction($action));
        }
    }

    public function test_validation_strategy_digest_selects_one_strategy_only(): void
    {
        $booking = $this->makeFreedomPkBooking([
            'offer_refresh' => [
                'refresh_result' => 'ok',
                'refresh_status' => 'refreshed',
                'accepted' => true,
            ],
            'selected_payload_style' => 'passenger_records_v2_5_gds',
        ]);
        $selection = app(SupplierValidationStrategySelector::class)
            ->selectForBooking($booking, SupplierValidationActionCode::GDS_PRE_PNR_FRESHNESS);

        $this->assertNotNull($selection['selected_strategy'] ?? null);
        $this->assertFalse((bool) ($selection['automatic_multi_strategy_retry'] ?? true));
        $eligible = is_array($selection['eligible_strategies'] ?? null) ? $selection['eligible_strategies'] : [];
        $this->assertContains($selection['selected_strategy'], $eligible);
    }

    public function test_digest_command_is_read_only_and_reports_selection(): void
    {
        $booking = $this->makeFreedomPkBooking();
        $this->artisan('supplier:validation-strategy-digest', [
            '--booking' => (string) $booking->id,
            '--action' => SupplierValidationActionCode::GDS_PRE_PNR_FRESHNESS,
            '--confirm' => SupplierValidationStrategyDigestCommand::CONFIRM_PHRASE,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('automatic_multi_strategy_retry=false')
            ->expectsOutputToContain('selected_strategy=');
    }

    /**
     * @param  array<string, mixed>  $metaExtra
     */
    protected function makeFreedomPkBooking(array $metaExtra = []): Booking
    {
        $agencyId = Agency::query()->value('id');
        $booking = Booking::query()->create([
            'agency_id' => $agencyId,
            'booking_reference' => 'BK-VAL-STRAT-'.uniqid(),
            'status' => 'pending',
            'meta' => array_merge([
                'supplier_connection_id' => 2,
                'selected_payload_style' => 'passenger_records_v2_5_gds',
                'sabre_booking_context' => [
                    'validating_carrier' => 'PK',
                    'brand_code' => 'FL',
                    'fare_basis_codes_by_segment' => ['VOWFL/V'],
                    'booking_classes_by_segment' => ['V'],
                ],
            ], $metaExtra),
        ]);
        BookingPassenger::query()->create([
            'booking_id' => $booking->id,
            'passenger_type' => 'ADT',
            'first_name' => 'Test',
            'last_name' => 'Traveler',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'booker@example.com',
            'phone' => '3001234567',
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'currency' => 'PKR',
            'total_amount' => 88623,
        ]);

        return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }
}
