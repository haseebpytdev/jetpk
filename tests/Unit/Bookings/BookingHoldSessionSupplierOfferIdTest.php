<?php

namespace Tests\Unit\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\BookingHoldSession;
use App\Models\SupplierConnection;
use App\Services\Bookings\FareHoldService;
use App\Support\Bookings\BookingHoldSessionSupplierOfferIdResolver;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingHoldSessionSupplierOfferIdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_resolver_prefers_short_offer_id_over_long_raw_reference(): void
    {
        $longRawReference = 'PIA-NDC-OFFER-'.str_repeat('X', 320);
        $shortOfferId = 'pia-ndc-a1b2c3d4e5f67890';

        $resolved = BookingHoldSessionSupplierOfferIdResolver::resolve([
            'offer_id' => $shortOfferId,
            'raw_reference' => $longRawReference,
        ], 'fallback-offer');

        $this->assertSame($shortOfferId, $resolved);
    }

    public function test_long_pia_ndc_raw_reference_hold_session_insert_preserves_full_context(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::PiaNdc,
            'is_active' => true,
        ]);
        $longRawReference = 'PIA-NDC-OFFER-'.str_repeat('Y', 320);
        $shortOfferId = 'pia-ndc-holdsession01';
        $providerContext = [
            'offer_ref_id' => $longRawReference,
            'offer_item_ref_id' => 'ITEM-PIA-1',
            'correlation_id' => 'corr-hold-1',
        ];
        $normalizedOffer = [
            'offer_id' => $shortOfferId,
            'supplier_provider' => SupplierProvider::PiaNdc->value,
            'supplier_connection_id' => $connection->id,
            'raw_reference' => $longRawReference,
            'raw_payload' => ['provider_context' => $providerContext],
            'provider_context' => $providerContext,
            'total' => 25800.0,
            'currency' => 'PKR',
            'expires_at' => now()->addHour()->toIso8601String(),
            'fare_breakdown' => [
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0, 'total' => 1],
            ],
            'pricing_components' => [],
        ];

        $session = app(FareHoldService::class)->refreshHoldSession(
            agency: $agency,
            booking: null,
            searchId: 'search-pia-hold-1',
            offerId: $shortOfferId,
            normalizedOffer: $normalizedOffer,
            user: null,
            holdStatus: 'not_started',
            safeError: null,
        );

        $this->assertInstanceOf(BookingHoldSession::class, $session);
        $this->assertDatabaseHas('booking_hold_sessions', [
            'id' => $session->id,
            'offer_id' => $shortOfferId,
            'supplier_offer_id' => $shortOfferId,
            'supplier_provider' => SupplierProvider::PiaNdc->value,
        ]);

        $snapshot = is_array($session->validated_offer_snapshot) ? $session->validated_offer_snapshot : [];
        $this->assertSame($longRawReference, $snapshot['raw_reference'] ?? null);
        $this->assertSame($longRawReference, $snapshot['provider_context']['offer_ref_id'] ?? null);
        $this->assertSame($longRawReference, $snapshot['raw_payload']['provider_context']['offer_ref_id'] ?? null);
        $this->assertGreaterThan(255, strlen((string) ($snapshot['raw_reference'] ?? '')));
    }
}
