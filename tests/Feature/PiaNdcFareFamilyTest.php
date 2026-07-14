<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\PiaNdcOptionPnrService;
use App\Support\Bookings\PiaNdcBookingProviderContextResolver;
use App\Support\Bookings\PiaNdcFareFamilyPolicy;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PiaNdcFareFamilyTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_backed_options_preserve_member_baggage(): void
    {
        $offer = $this->groupedPiaOfferSnapshot();
        $offer['itinerary_fare_group'] = [
            'members_by_id' => [
                'pia-eco' => [
                    'offer_id' => 'pia-eco',
                    'baggage' => ['checked' => '20 KG', 'cabin' => '7 KG', 'summary' => '20 KG'],
                    'refundable' => false,
                ],
                'pia-smart' => [
                    'offer_id' => 'pia-smart',
                    'baggage' => ['checked' => '30 KG', 'cabin' => '7 KG', 'summary' => '30 KG'],
                    'refundable' => false,
                ],
                'pia-freedom' => [
                    'offer_id' => 'pia-freedom',
                    'baggage' => ['checked' => '40 KG', 'cabin' => '7 KG', 'summary' => '40 KG'],
                    'refundable' => true,
                ],
            ],
        ];

        foreach ($offer['fare_family_options'] as $idx => $option) {
            $offer['fare_family_options'][$idx] = array_merge($option, [
                'source_offer_id' => match ($option['name'] ?? '') {
                    'ECO LIGHT' => 'pia-eco',
                    'SMART' => 'pia-smart',
                    'FREEDOM' => 'pia-freedom',
                    default => null,
                },
                'check_in_summary' => match ($option['name'] ?? '') {
                    'ECO LIGHT' => '20 KG',
                    'SMART' => '30 KG',
                    'FREEDOM' => '40 KG',
                    default => null,
                },
            ]);
        }

        $presentation = FlightOfferDisplayPresenter::buildBrandedFaresPresentationFields([], $offer);
        $options = $presentation['fare_family_options_display'];

        $this->assertSame('20 kg', $options[0]['check_in_summary'] ?? null);
        $this->assertSame('30 kg', $options[1]['check_in_summary'] ?? null);
        $this->assertSame('40 kg', $options[2]['check_in_summary'] ?? null);
        $this->assertSame('7 kg', $options[0]['carry_on_summary'] ?? null);
    }

    public function test_shows_eco_light_smart_freedom_only_when_each_has_provider_context(): void
    {
        $offer = $this->groupedPiaOfferSnapshot();
        $presentation = FlightOfferDisplayPresenter::buildBrandedFaresPresentationFields([], $offer);

        $this->assertTrue($presentation['branded_fares_selection_active']);
        $this->assertCount(3, $presentation['fare_family_options_display']);

        $names = array_column($presentation['fare_family_options_display'], 'name');
        $this->assertSame(['ECO LIGHT', 'SMART', 'FREEDOM'], $names);

        foreach ($presentation['fare_family_options_display'] as $option) {
            $this->assertTrue($option['pia_ndc_provider_backed'] ?? false);
            $this->assertNotSame('', $option['provider_context']['offer_item_ref_id'] ?? '');
        }
    }

    public function test_only_eco_light_when_sibling_brands_lack_provider_context(): void
    {
        $offer = $this->groupedPiaOfferSnapshot(includeSmartFreedom: false);
        $presentation = FlightOfferDisplayPresenter::buildBrandedFaresPresentationFields([
            ['name' => 'FREEDOM', 'option_key' => 'freedom-key', 'price_total' => 28590],
            ['name' => 'SMART', 'option_key' => 'smart-key', 'price_total' => 26590],
        ], $offer);

        $this->assertFalse($presentation['branded_fares_selection_active']);
        $this->assertCount(1, $presentation['fare_family_options_display']);
        $this->assertSame('ECO LIGHT', $presentation['fare_family_options_display'][0]['name']);
    }

    public function test_selecting_freedom_persists_freedom_provider_context(): void
    {
        $offer = $this->groupedPiaOfferSnapshot();
        $freedom = collect(PiaNdcFareFamilyPolicy::collectProviderBackedBrandOptions($offer))
            ->firstWhere('name', 'FREEDOM');
        $this->assertNotNull($freedom);

        $intent = FlightOfferDisplayPresenter::sanitizeSelectedFareFamilyIntent($freedom, $offer);
        $this->assertSame('FREEDOM', $intent['name'] ?? null);
        $this->assertSame('OfferItem-5', $intent['provider_context']['offer_item_ref_id'] ?? null);
        $this->assertSame('freedom-offer-ref', $intent['provider_context']['offer_ref_id'] ?? null);

        $validated = PiaNdcFareFamilyPolicy::applySelectedBrandToValidatedSnapshot($offer, $intent);
        $this->assertSame('FREEDOM', $validated['fare_family'] ?? null);
        $this->assertSame('OfferItem-5', $validated['provider_context']['offer_item_ref_id'] ?? null);
    }

    public function test_order_create_uses_selected_brand_offer_refs(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $connection = $this->piaConnection();
        $offer = $this->groupedPiaOfferSnapshot();
        $freedom = collect(PiaNdcFareFamilyPolicy::collectProviderBackedBrandOptions($offer))
            ->firstWhere('name', 'FREEDOM');
        $this->assertNotNull($freedom);

        $intent = FlightOfferDisplayPresenter::sanitizeSelectedFareFamilyIntent($freedom, $offer);
        $validated = PiaNdcFareFamilyPolicy::applySelectedBrandToValidatedSnapshot($offer, $intent);
        $booking = $this->piaDraftBooking($connection, [
            'validated_offer_snapshot' => $validated,
            'selected_fare_family_option' => $intent,
        ]);

        Http::fake([
            'example.test/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCreate_OW_res.xml')),
                200,
                ['Content-Type' => 'text/xml; charset=utf-8'],
            ),
        ]);

        app(PiaNdcOptionPnrService::class)->autoCreateOptionPnrForPublicBooking($booking);

        $resolved = app(PiaNdcBookingProviderContextResolver::class)->resolve($booking->fresh());
        $this->assertSame('freedom-offer-ref', $resolved['context']['offer_ref_id'] ?? null);
        $this->assertSame('OfferItem-5', $resolved['context']['offer_item_ref_id'] ?? null);
        $this->assertSame('booking.meta.selected_fare_family_option', $resolved['source']);

        Http::assertSent(function ($request): bool {
            $body = (string) $request->body();

            return str_contains($body, 'freedom-offer-ref') && str_contains($body, 'OfferItem-5');
        });
    }

    public function test_mismatch_is_blocked_before_supplier_call(): void
    {
        $connection = $this->piaConnection();
        $offer = $this->groupedPiaOfferSnapshot();
        $freedomIntent = [
            'name' => 'FREEDOM',
            'option_key' => 'pia-ndc-brand-freedom',
            'provider_context' => [
                'shopping_response_ref_id' => 'shop-ref',
                'offer_ref_id' => 'freedom-offer-ref',
                'offer_item_ref_id' => 'OfferItem-5',
                'fare_type_code' => 'FREEDOM',
                'fare_basis' => 'FNBAG',
                'rbd' => 'F',
                'owner_code' => 'PK',
            ],
        ];

        $booking = $this->piaDraftBooking($connection, [
            'validated_offer_snapshot' => array_merge($offer, [
                'provider_context' => $offer['fare_family_options'][0]['provider_context'],
            ]),
            'selected_fare_family_option' => $freedomIntent,
        ]);

        $this->assertFalse(PiaNdcFareFamilyPolicy::selectedIntentMatchesValidatedSnapshot($booking));

        Http::fake();
        $result = app(PiaNdcOptionPnrService::class)->autoCreateOptionPnrForPublicBooking($booking);
        $this->assertFalse($result['success']);
        $this->assertTrue($result['summary']['skipped'] ?? false);
        Http::assertNothingSent();
    }

    public function test_sanitize_does_not_fallback_to_eco_light_when_freedom_context_unresolved(): void
    {
        $offer = $this->groupedPiaOfferSnapshot();
        $freedom = collect(PiaNdcFareFamilyPolicy::collectProviderBackedBrandOptions($offer))
            ->firstWhere('name', 'FREEDOM');
        $this->assertNotNull($freedom);

        $strippedSnapshot = $offer;
        unset($strippedSnapshot['fare_family_options'], $strippedSnapshot['itinerary_fare_group']);

        $sanitized = PiaNdcFareFamilyPolicy::sanitizeSelectedIntentForPiaNdc($freedom, $strippedSnapshot);
        $this->assertNull($sanitized);
    }

    public function test_find_fare_option_key_matches_provider_backed_display_options(): void
    {
        $offer = $this->groupedPiaOfferSnapshot();
        $freedom = collect(PiaNdcFareFamilyPolicy::collectProviderBackedBrandOptions($offer))
            ->firstWhere('name', 'FREEDOM');
        $this->assertNotNull($freedom);

        $resolved = FlightOfferDisplayPresenter::findFareFamilyOptionByKey(
            $offer,
            (string) ($freedom['option_key'] ?? ''),
        );
        $this->assertNotNull($resolved);
        $this->assertSame('FREEDOM', $resolved['name'] ?? null);
        $this->assertSame('OfferItem-5', $resolved['provider_context']['offer_item_ref_id'] ?? null);
    }

    public function test_reconcile_booking_meta_preserves_selected_when_sanitize_fails(): void
    {
        $offer = $this->groupedPiaOfferSnapshot();
        $freedom = collect(PiaNdcFareFamilyPolicy::collectProviderBackedBrandOptions($offer))
            ->firstWhere('name', 'FREEDOM');
        $this->assertNotNull($freedom);

        $strippedSnapshot = array_merge($offer, [
            'provider_context' => $offer['fare_family_options'][0]['provider_context'],
        ]);
        unset($strippedSnapshot['fare_family_options'], $strippedSnapshot['itinerary_fare_group']);

        $meta = [
            'supplier_provider' => SupplierProvider::PiaNdc->value,
            'validated_offer_snapshot' => $strippedSnapshot,
            'selected_fare_family_option' => $freedom,
        ];

        $reconciled = PiaNdcFareFamilyPolicy::reconcileBookingMeta($meta);
        $this->assertSame('FREEDOM', $reconciled['selected_fare_family_option']['name'] ?? null);
        $this->assertSame('ECO LIGHT', $reconciled['validated_offer_snapshot']['provider_context']['fare_type_code'] ?? null);
    }

    public function test_same_brand_different_products_remain_selectable_with_disambiguator(): void
    {
        $offer = $this->groupedPiaOfferSnapshot();
        $presentation = FlightOfferDisplayPresenter::buildBrandedFaresPresentationFields([], $offer);

        $this->assertCount(3, $presentation['fare_family_options_display']);
        foreach ($presentation['fare_family_options_display'] as $option) {
            $this->assertTrue($option['pia_ndc_provider_backed'] ?? false);
            $this->assertNotSame('', $option['provider_context']['offer_item_ref_id'] ?? '');
            $this->assertNotSame('', $option['fare_variant_subtitle'] ?? $option['fare_product_disambiguator'] ?? '');
        }

        $eco = collect($presentation['fare_family_options_display'])->firstWhere('name', 'ECO LIGHT');
        $this->assertNotNull($eco);
        $this->assertStringContainsString('VNBAG', (string) ($eco['fare_variant_subtitle'] ?? ''));
        $this->assertStringContainsString('Class V', (string) ($eco['fare_variant_subtitle'] ?? ''));
    }

    public function test_selected_freedom_carries_offer_refs_and_fare_basis_into_checkout(): void
    {
        $offer = $this->groupedPiaOfferSnapshot();
        $freedom = collect(PiaNdcFareFamilyPolicy::collectProviderBackedBrandOptions($offer))
            ->firstWhere('name', 'FREEDOM');
        $this->assertNotNull($freedom);

        $intent = FlightOfferDisplayPresenter::sanitizeSelectedFareFamilyIntent($freedom, $offer);
        $validated = PiaNdcFareFamilyPolicy::applySelectedBrandToValidatedSnapshot($offer, $intent);

        $this->assertSame('freedom-offer-ref', $validated['provider_context']['offer_ref_id'] ?? null);
        $this->assertSame('OfferItem-5', $validated['provider_context']['offer_item_ref_id'] ?? null);
        $this->assertSame('FNBAG', $intent['fare_basis'] ?? $intent['provider_context']['fare_basis'] ?? null);
        $this->assertSame('FNBAG', $validated['provider_context']['fare_basis'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function groupedPiaOfferSnapshot(bool $includeSmartFreedom = true): array
    {
        $ecoCtx = [
            'provider' => SupplierProvider::PiaNdc->value,
            'shopping_response_ref_id' => 'b00fe7be-88f0-4de3-b455-28b5aa20f767',
            'offer_ref_id' => 'eco-offer-ref',
            'offer_item_ref_id' => 'OfferItem-1',
            'pax_ref_id' => 'ADTPax-1',
            'owner_code' => 'PK',
            'payment_time_limit' => '2099-12-31T23:59:59',
            'fare_type_code' => 'ECO LIGHT',
            'fare_basis' => 'VNBAG',
            'rbd' => 'V',
        ];

        $fareFamilyOptions = [
            PiaNdcFareFamilyPolicy::buildProviderBackedFareFamilyOptionFromRow(
                ['supplier_provider' => SupplierProvider::PiaNdc->value, 'fare_breakdown' => ['supplier_total' => 24410]],
                ['name' => 'ECO LIGHT', 'price_total' => 24410, 'source_offer_id' => 'pia-eco'],
                $ecoCtx,
            ),
        ];

        if ($includeSmartFreedom) {
            $fareFamilyOptions[] = PiaNdcFareFamilyPolicy::buildProviderBackedFareFamilyOptionFromRow(
                ['supplier_provider' => SupplierProvider::PiaNdc->value, 'fare_breakdown' => ['supplier_total' => 26590]],
                ['name' => 'SMART', 'price_total' => 26590, 'source_offer_id' => 'pia-smart'],
                [
                    ...$ecoCtx,
                    'offer_ref_id' => 'smart-offer-ref',
                    'offer_item_ref_id' => 'OfferItem-3',
                    'fare_type_code' => 'SMART',
                    'fare_basis' => 'SNBAG',
                    'rbd' => 'S',
                ],
            );
            $fareFamilyOptions[] = PiaNdcFareFamilyPolicy::buildProviderBackedFareFamilyOptionFromRow(
                ['supplier_provider' => SupplierProvider::PiaNdc->value, 'fare_breakdown' => ['supplier_total' => 28590]],
                ['name' => 'FREEDOM', 'price_total' => 28590, 'source_offer_id' => 'pia-freedom'],
                [
                    ...$ecoCtx,
                    'offer_ref_id' => 'freedom-offer-ref',
                    'offer_item_ref_id' => 'OfferItem-5',
                    'fare_type_code' => 'FREEDOM',
                    'fare_basis' => 'FNBAG',
                    'rbd' => 'F',
                ],
            );
        }

        $fareFamilyOptions = array_values(array_filter($fareFamilyOptions));

        return [
            'id' => 'pia-ndc-grouped-offer',
            'offer_id' => 'pia-ndc-grouped-offer',
            'supplier_provider' => SupplierProvider::PiaNdc->value,
            'fare_family' => 'ECO LIGHT',
            'provider_context' => $ecoCtx,
            'raw_payload' => ['provider_context' => $ecoCtx],
            'fare_breakdown' => ['supplier_total' => 24410],
            'segments' => [
                [
                    'airline_code' => 'PK',
                    'flight_number' => '233',
                    'origin' => 'ISB',
                    'destination' => 'DXB',
                    'departure_at' => '2026-08-01T10:00:00',
                    'arrival_at' => '2026-08-01T14:00:00',
                ],
            ],
            'fare_family_options' => $fareFamilyOptions,
            'has_grouped_fare_options' => $includeSmartFreedom,
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

    /**
     * @param  array<string, mixed>  $metaOverrides
     */
    private function piaDraftBooking(SupplierConnection $connection, array $metaOverrides = []): Booking
    {
        $snapshot = array_merge($this->groupedPiaOfferSnapshot(), [
            'supplier_connection_id' => $connection->id,
        ]);

        $booking = Booking::factory()->create([
            'booking_reference' => 'PIAFAM01',
            'supplier' => SupplierProvider::PiaNdc->value,
            'payment_status' => 'unpaid',
            'status' => BookingStatus::Draft,
            'pnr' => null,
            'meta' => array_merge([
                'supplier_provider' => SupplierProvider::PiaNdc->value,
                'supplier_connection_id' => $connection->id,
                'validated_offer_snapshot' => $snapshot,
                'flight_offer_snapshot' => $snapshot,
                'search_criteria' => [
                    'origin' => 'ISB',
                    'destination' => 'DXB',
                    'depart_date' => now()->addDays(14)->format('Y-m-d'),
                ],
            ], $metaOverrides),
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'first_name' => 'JOHN',
            'last_name' => 'DOE',
            'date_of_birth' => '1990-01-01',
            'is_lead_passenger' => true,
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'john.doe@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact']);
    }
}
