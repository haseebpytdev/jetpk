<?php

namespace Tests\Unit\Support\Suppliers;

use App\Data\FlightSearchRequestData;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcOfferShopRequestBuilder;
use App\Support\Suppliers\SabreNdcGroupedItineraryDiagnostics;
use App\Support\Suppliers\SabreNdcNoOfferReasonClassifier;
use Tests\TestCase;

class SabreNdcGroupedItineraryDiagnosticsTest extends TestCase
{
    public function test_http_200_empty_gir_classified_as_ndc_zero_offers(): void
    {
        $shape = app(SabreNdcGroupedItineraryDiagnostics::class)->summarize([
            'groupedItineraryResponse' => [
                'itineraryGroups' => [],
                'scheduleDescs' => [],
                'legDescs' => [],
                'messages' => [],
            ],
        ]);

        $reason = SabreNdcNoOfferReasonClassifier::classify(array_merge($shape, [
            'http_status' => 200,
            'reason_code' => 'sabre_ndc_zero_offers',
            'normalized_offer_count' => 0,
        ]));

        $this->assertSame('grouped_itinerary', $shape['response_shape']);
        $this->assertSame('ndc_zero_offers', $reason);
    }

    public function test_http_200_with_raw_itineraries_but_normalized_zero_is_parser_issue(): void
    {
        $shape = app(SabreNdcGroupedItineraryDiagnostics::class)->summarize([
            'groupedItineraryResponse' => [
                'scheduleDescs' => [['ref' => 1]],
                'legDescs' => [['ref' => 1]],
                'itineraryGroups' => [[
                    'itineraries' => [[
                        'id' => 'raw-only',
                        'pricingInformation' => [['offerItemId' => 'x']],
                    ]],
                ]],
                'messages' => [],
            ],
        ]);

        $reason = SabreNdcNoOfferReasonClassifier::classify(array_merge($shape, [
            'http_status' => 200,
            'reason_code' => 'sabre_ndc_zero_offers',
            'normalized_offer_count' => 0,
        ]));

        $this->assertSame('ndc_parser_zero_offers', $reason);
    }

    public function test_entitlement_message_classified_safely(): void
    {
        $reason = SabreNdcNoOfferReasonClassifier::classify([
            'http_status' => 200,
            'reason_code' => 'sabre_ndc_zero_offers',
            'response_shape' => 'grouped_itinerary',
            'offer_count_raw' => 0,
            'normalized_offer_count' => 0,
            'message_rows' => [[
                'type' => 'warning',
                'code' => 'NDC.NOT.ENABLED',
                'message' => 'NDC content not available for this PCC.',
            ]],
        ]);

        $this->assertSame('ndc_entitlement_or_permission_error', $reason);
    }

    public function test_public_default_variant_is_pos_pcc_source(): void
    {
        config(['suppliers.sabre.ndc.search_request_variant' => null]);

        $builder = app(SabreNdcOfferShopRequestBuilder::class);
        $this->assertSame(
            SabreNdcOfferShopRequestBuilder::VARIANT_POS_PCC_SOURCE,
            $builder->resolvePublicSearchVariant(null),
        );
    }

    public function test_diagnostic_gir_variant_not_used_for_public_search(): void
    {
        config(['suppliers.sabre.ndc.search_request_variant' => SabreNdcOfferShopRequestBuilder::VARIANT_GIR_DATASOURCES_ONLY]);

        $builder = app(SabreNdcOfferShopRequestBuilder::class);
        $this->assertTrue($builder->isDiagnosticOnlyVariant(SabreNdcOfferShopRequestBuilder::VARIANT_GIR_DATASOURCES_ONLY));
        $this->assertSame(
            SabreNdcOfferShopRequestBuilder::VARIANT_POS_PCC_SOURCE,
            $builder->resolvePublicSearchVariant(null),
        );
        $this->assertSame(
            SabreNdcOfferShopRequestBuilder::VARIANT_GIR_DATASOURCES_ONLY,
            $builder->selectedVariant(SabreNdcOfferShopRequestBuilder::VARIANT_GIR_DATASOURCES_ONLY),
        );
    }

    public function test_diagnostic_data_source_variants_are_cli_only(): void
    {
        $builder = app(SabreNdcOfferShopRequestBuilder::class);

        foreach ([
            SabreNdcOfferShopRequestBuilder::VARIANT_NDC_ONLY,
            SabreNdcOfferShopRequestBuilder::VARIANT_NDC_PLUS_ATPCO_DIAGNOSTIC,
            SabreNdcOfferShopRequestBuilder::VARIANT_ATPCO_ONLY_DIAGNOSTIC,
        ] as $variant) {
            $this->assertTrue($builder->isDiagnosticOnlyVariant($variant));
            $this->assertSame(
                SabreNdcOfferShopRequestBuilder::VARIANT_POS_PCC_SOURCE,
                $builder->resolvePublicSearchVariant($variant),
            );
        }
    }

    public function test_operating_carrier_mode_is_unsupported_safely(): void
    {
        $builder = app(SabreNdcOfferShopRequestBuilder::class);
        $connection = new SupplierConnection([
            'credentials' => ['pcc' => 'NDCS'],
        ]);
        $payload = $builder->build(
            new FlightSearchRequestData(origin: 'LHE', destination: 'DXB', departure_date: '2026-07-16', adults: 1),
            $connection,
            SabreNdcOfferShopRequestBuilder::VARIANT_POS_PCC_SOURCE,
        );

        $meta = $builder->finalizePayload($payload, [
            'carrier_code' => 'EK',
            'carrier_mode' => 'operating',
        ]);

        $this->assertTrue($meta['unsupported_carrier_filter']);
        $this->assertFalse($meta['carrier_filter_applied']);
    }
}
