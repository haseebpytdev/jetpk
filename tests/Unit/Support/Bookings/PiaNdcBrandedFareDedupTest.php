<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Support\Bookings\PiaNdcBrandedFareDedup;
use App\Support\Bookings\PiaNdcFareFamilyPolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PiaNdcBrandedFareDedupTest extends TestCase
{
    #[Test]
    public function test_drops_exact_duplicate_fare_cards(): void
    {
        $ctx = [
            'shopping_response_ref_id' => 'shop-ref',
            'offer_ref_id' => 'offer-a',
            'offer_item_ref_id' => 'OfferItem-1',
            'fare_basis' => 'VNBAG',
            'rbd' => 'V',
            'fare_type_code' => 'FREEDOM',
            'owner_code' => 'PK',
        ];
        $offer = [
            'offer_id' => 'pia-parent',
            'supplier_provider' => SupplierProvider::PiaNdc->value,
            'cabin' => 'economy',
            'fare_breakdown' => ['supplier_total' => 24410, 'currency' => 'PKR'],
        ];
        $row = PiaNdcFareFamilyPolicy::buildProviderBackedFareFamilyOptionFromRow(
            $offer,
            ['name' => 'FREEDOM', 'price_total' => 24410],
            $ctx,
        );
        $this->assertNotNull($row);
        $duplicate = $row;
        $duplicate['option_key'] = 'duplicate-key';

        $result = PiaNdcBrandedFareDedup::dedupeOptions([$row, $duplicate], $offer);

        $this->assertSame(2, $result['stats']['before_count']);
        $this->assertSame(1, $result['stats']['after_count']);
        $this->assertSame(1, $result['stats']['dropped_duplicate_count']);
    }

    #[Test]
    public function test_keeps_same_brand_with_different_offer_item_and_adds_disambiguator(): void
    {
        $offer = [
            'offer_id' => 'pia-parent',
            'supplier_provider' => SupplierProvider::PiaNdc->value,
            'cabin' => 'economy',
            'fare_breakdown' => ['supplier_total' => 24410, 'currency' => 'PKR'],
        ];
        $freedomA = PiaNdcFareFamilyPolicy::buildProviderBackedFareFamilyOptionFromRow(
            $offer,
            ['name' => 'FREEDOM', 'price_total' => 24410, 'booking_class' => 'F', 'fare_basis' => 'FNBAG'],
            [
                'shopping_response_ref_id' => 'shop',
                'offer_ref_id' => 'offer-a',
                'offer_item_ref_id' => 'OfferItem-1',
                'fare_basis' => 'FNBAG',
                'rbd' => 'F',
                'fare_type_code' => 'FREEDOM',
                'owner_code' => 'PK',
            ],
        );
        $freedomB = PiaNdcFareFamilyPolicy::buildProviderBackedFareFamilyOptionFromRow(
            $offer,
            ['name' => 'FREEDOM', 'price_total' => 26590, 'booking_class' => 'S', 'fare_basis' => 'SNBAG'],
            [
                'shopping_response_ref_id' => 'shop',
                'offer_ref_id' => 'offer-b',
                'offer_item_ref_id' => 'OfferItem-2',
                'fare_basis' => 'SNBAG',
                'rbd' => 'S',
                'fare_type_code' => 'FREEDOM',
                'owner_code' => 'PK',
            ],
        );

        $result = PiaNdcBrandedFareDedup::dedupeOptions([$freedomA, $freedomB], $offer);

        $this->assertCount(2, $result['options']);
        $this->assertSame(0, $result['stats']['dropped_duplicate_count']);
        $this->assertCount(1, $result['stats']['same_brand_different_product_groups']);
        $this->assertNotSame('', $result['options'][0]['fare_product_disambiguator'] ?? '');
        $this->assertNotSame('', $result['options'][1]['fare_product_disambiguator'] ?? '');
    }

    #[Test]
    public function test_build_variant_subtitle_uses_fare_basis_baggage_and_class(): void
    {
        $subtitle = PiaNdcBrandedFareDedup::buildVariantSubtitle([
            'fare_basis' => 'VNBAG',
            'booking_class' => 'V',
            'check_in_summary' => '20 KG',
        ]);

        $this->assertSame('VNBAG · 20 kg · Class V', $subtitle);
    }

    #[Test]
    public function test_enrich_variant_presentation_applies_to_all_options(): void
    {
        $options = PiaNdcBrandedFareDedup::enrichVariantPresentation([
            [
                'name' => 'ECO LIGHT',
                'fare_basis' => 'VNBAG',
                'check_in_summary' => '0 kg',
            ],
            [
                'name' => 'SMART',
                'fare_basis' => 'VSM',
                'check_in_summary' => '20 kg',
                'booking_class' => 'S',
            ],
        ]);

        $this->assertSame('VNBAG · 0 kg', $options[0]['fare_variant_subtitle'] ?? null);
        $this->assertSame('VSM · 20 kg · Class S', $options[1]['fare_variant_subtitle'] ?? null);
    }

    #[Test]
    public function test_identity_key_prefers_offer_item_ref(): void
    {
        $offer = ['supplier_provider' => SupplierProvider::PiaNdc->value, 'cabin' => 'economy'];
        $row = [
            'name' => 'FREEDOM',
            'brand_name' => 'FREEDOM',
            'price_total' => 10000,
            'currency' => 'PKR',
            'fare_basis' => 'FNBAG',
            'booking_class' => 'F',
            'provider_context' => [
                'offer_ref_id' => 'offer-a',
                'offer_item_ref_id' => 'OfferItem-9',
                'fare_basis' => 'FNBAG',
                'rbd' => 'F',
            ],
        ];

        $keyA = PiaNdcBrandedFareDedup::identityKey($row, $offer);
        $row['price_total'] = 10001;
        $keyB = PiaNdcBrandedFareDedup::identityKey($row, $offer);

        $this->assertNotSame($keyA, $keyB);
    }
}
