<?php

namespace Tests\Unit\Support\FlightSearch;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Iati\IatiResponseNormalizer;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IatiBrandedFarePresentationTest extends TestCase
{
    #[Test]
    public function test_iati_branded_fare_selection_is_enabled_by_default(): void
    {
        $offer = ['supplier_provider' => 'iati'];
        $presentation = FlightOfferDisplayPresenter::buildBrandedFaresPresentationFields([
            ['name' => 'Basic', 'price_total' => 80000, 'currency' => 'PKR'],
            ['name' => 'Flex', 'price_total' => 90000, 'currency' => 'PKR'],
        ], $offer);

        $this->assertTrue($presentation['branded_fares_selection_active']);
        $this->assertTrue($presentation['has_branded_fares']);
        $this->assertCount(2, $presentation['fare_family_options_display']);
    }

    #[Test]
    public function test_branded_option_display_price_includes_customer_markup(): void
    {
        $offer = [
            'supplier_provider' => 'iati',
            'supplier_total_source' => 80000,
            'final_customer_price' => 84309,
            'markup' => 4015,
            'service_fee' => 0,
            'pricing_currency' => 'PKR',
        ];

        $priced = FlightOfferDisplayPresenter::deriveBrandedFareOptionDisplayPrice([
            'price_total' => 80000,
            'currency' => 'PKR',
        ], $offer);

        $this->assertSame(84309, $priced['displayed_price']);
    }

    #[Test]
    public function test_branded_option_baggage_maps_one_to_one_from_iati_normalizer(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/iati/search_response_branded_baggage.json')),
            true,
        );

        $offers = app(IatiResponseNormalizer::class)->normalizeSearchResponse(
            $fixture,
            new SupplierConnection([
                'id' => 1,
                'provider' => SupplierProvider::Iati,
                'environment' => SupplierEnvironment::Sandbox,
            ]),
            'corr-present-bag',
            1,
            0,
            0,
        );

        $offer = $offers[0]->toArray();
        $presentation = FlightOfferDisplayPresenter::buildPresentation($offer, [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'trip_type' => 'one_way',
        ], []);

        $options = $presentation['fare_family_options_display'];
        $this->assertCount(3, $options);
        $this->assertSame('0 kg', $options[0]['check_in_summary'] ?? null);
        $this->assertSame('20 kg', $options[1]['check_in_summary'] ?? null);
        $this->assertSame('30 kg', $options[2]['check_in_summary'] ?? null);
        $this->assertSame('1 piece', $options[0]['carry_on_summary'] ?? null);
        $this->assertStringNotContainsString('KILO', (string) ($options[2]['check_in_summary'] ?? ''));
        $this->assertStringNotContainsString('PIECE', (string) ($options[2]['carry_on_summary'] ?? ''));
    }

    #[Test]
    public function test_non_branded_offer_exposes_synthetic_default_fare_option(): void
    {
        $offer = [
            'supplier_provider' => 'iati',
            'offer_id' => 'test-offer-default-1',
            'cabin' => 'economy',
            'supplier_total' => 50000,
            'supplier_currency' => 'PKR',
            'final_customer_price' => 52000,
            'displayed_price' => 52000,
            'markup' => 2000,
            'baggage' => ['checked' => '20 kg', 'cabin' => '7 kg'],
            'refund_rule' => 'Non-refundable',
            'change_rule' => 'Changes allowed with fee',
        ];

        $presentation = FlightOfferDisplayPresenter::buildBrandedFaresPresentationFields([], $offer);

        $this->assertTrue($presentation['has_fare_choice_options']);
        $this->assertTrue($presentation['has_synthetic_default_fare']);
        $this->assertTrue($presentation['universal_fare_selection_active']);
        $this->assertFalse($presentation['has_multiple_fare_choices']);
        $this->assertFalse($presentation['has_branded_fares']);
        $this->assertCount(1, $presentation['fare_family_options_display']);

        $option = $presentation['fare_family_options_display'][0];
        $this->assertSame('Economy Fare', $option['name']);
        $this->assertTrue($option['is_synthetic_default']);
        $this->assertTrue($option['selectable']);
        $this->assertFalse($option['display_only']);
        $this->assertStringStartsWith('standard-fare-', (string) $option['option_key']);
        $this->assertSame('20 kg', $option['check_in_summary'] ?? null);
        $this->assertSame(52000, $option['displayed_price'] ?? null);
    }

    #[Test]
    public function test_non_branded_offer_with_supplier_total_source_exposes_synthetic_default_with_display_price(): void
    {
        $offer = [
            'supplier_provider' => 'iati',
            'offer_id' => 'test-offer-source-1',
            'cabin' => 'economy',
            'supplier_total_source' => 48000,
            'supplier_currency' => 'PKR',
            'pricing_currency' => 'PKR',
            'final_customer_price' => 52000,
            'markup' => 4000,
            'baggage' => ['checked' => '15 kg', 'cabin' => '7 kg'],
        ];

        $presentation = FlightOfferDisplayPresenter::buildBrandedFaresPresentationFields([], $offer);
        $option = $presentation['fare_family_options_display'][0] ?? [];

        $this->assertTrue($presentation['has_synthetic_default_fare']);
        $this->assertTrue($option['is_synthetic_default'] ?? false);
        $this->assertNotNull($option['price_total'] ?? null);
        $this->assertSame(52000, $option['displayed_price'] ?? null);
        $this->assertStringStartsWith('standard-fare-', (string) ($option['option_key'] ?? ''));
    }

    #[Test]
    public function test_sabre_non_branded_offer_exposes_synthetic_default_fare_option(): void
    {
        $offer = [
            'supplier_provider' => 'sabre',
            'offer_id' => 'sabre-default-1',
            'supplier_total_source' => 75000,
            'pricing_currency' => 'PKR',
            'final_customer_price' => 78000,
            'markup' => 3000,
        ];

        $presentation = FlightOfferDisplayPresenter::buildBrandedFaresPresentationFields([], $offer);

        $this->assertTrue($presentation['has_fare_choice_options']);
        $this->assertTrue($presentation['has_synthetic_default_fare']);
        $this->assertCount(1, $presentation['fare_family_options_display']);
        $this->assertSame('Standard Fare', $presentation['fare_family_options_display'][0]['name'] ?? null);
    }

    #[Test]
    public function test_branded_offer_still_exposes_multiple_fare_choices(): void
    {
        $offer = ['supplier_provider' => 'iati'];
        $presentation = FlightOfferDisplayPresenter::buildBrandedFaresPresentationFields([
            ['name' => 'Fare 1', 'option_key' => 'iati-fare-1', 'price_total' => 80294, 'currency' => 'PKR'],
            ['name' => 'Fare 2', 'option_key' => 'iati-fare-2-85158-1', 'price_total' => 85158, 'currency' => 'PKR'],
            ['name' => 'Fare 3', 'option_key' => 'iati-fare-3', 'price_total' => 90000, 'currency' => 'PKR'],
        ], $offer);

        $this->assertTrue($presentation['has_branded_fares']);
        $this->assertTrue($presentation['has_multiple_fare_choices']);
        $this->assertFalse($presentation['has_synthetic_default_fare']);
        $this->assertCount(3, $presentation['fare_family_options_display']);
        $this->assertSame('iati-fare-2-85158-1', $presentation['fare_family_options_display'][1]['option_key'] ?? null);
    }
}
