<?php

namespace Tests\Unit\Support\FlightSearch;

use App\Http\Controllers\Frontend\FlightController;
use App\Support\FlightSearch\ItineraryFareConsolidator;
use App\Support\FlightSearch\OfferBaggageResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Offline reproduction + permanent contract for structured offer.baggage
 * (AirBlue shape) in results filter metadata — zero supplier calls.
 */
class StructuredBaggageFilterSafetyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_legacy_string_coercion_fatals_on_structured_baggage(): void
    {
        $offer = $this->airblueNullStructuredOffer();
        $threw = false;
        set_error_handler(static function (int $severity, string $message): bool {
            if (str_contains($message, 'Array to string conversion')) {
                throw new \ErrorException($message, 0, $severity);
            }

            return false;
        });
        try {
            // Exact production failure pattern at FlightController::buildFilterMeta.
            $bag = strtolower((string) ($offer['baggage'] ?? ''));
            unset($bag);
        } catch (\ErrorException $e) {
            $threw = str_contains($e->getMessage(), 'Array to string conversion');
        } finally {
            restore_error_handler();
        }

        $this->assertTrue($threw, 'STRUCTURED_BAGGAGE_FATAL_REPRODUCED expected for legacy cast');
    }

    #[Test]
    public function test_airblue_null_structured_baggage_is_no_baggage_info(): void
    {
        $offer = $this->airblueNullStructuredOffer();
        $this->assertSame('no_baggage_info', OfferBaggageResolver::filterBucketFromOffer($offer));
        $this->assertSame('', OfferBaggageResolver::summaryTextFromOffer($offer));
        $this->assertSame([
            'checked' => null,
            'cabin' => null,
            'summary' => null,
        ], OfferBaggageResolver::resolveFromOffer($offer));
    }

    #[Test]
    #[DataProvider('legacyStringBaggageProvider')]
    public function test_legacy_string_baggage_buckets(string $baggage, string $expectedBucket): void
    {
        $this->assertSame(
            $expectedBucket,
            OfferBaggageResolver::filterBucketFromOffer(['baggage' => $baggage])
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function legacyStringBaggageProvider(): array
    {
        return [
            '20_kg' => ['20 kg', 'checked_baggage'],
            'checked_25' => ['Checked: 25 kg', 'checked_baggage'],
            'cabin_bag' => ['Cabin bag', 'cabin_baggage'],
            'empty' => ['', 'no_baggage_info'],
        ];
    }

    #[Test]
    #[DataProvider('structuredBaggageProvider')]
    public function test_structured_baggage_buckets(array $baggage, string $expectedBucket): void
    {
        $this->assertSame(
            $expectedBucket,
            OfferBaggageResolver::filterBucketFromOffer(['baggage' => $baggage])
        );
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function structuredBaggageProvider(): array
    {
        return [
            'checked_and_cabin' => [[
                'checked' => '20 kg',
                'cabin' => '7 kg',
                'summary' => '20 kg / 7 kg',
            ], 'checked_baggage'],
            'cabin_only' => [[
                'checked' => null,
                'cabin' => '7 kg',
                'summary' => '7 kg',
            ], 'cabin_baggage'],
            'all_null' => [[
                'checked' => null,
                'cabin' => null,
                'summary' => null,
            ], 'no_baggage_info'],
            'empty_array' => [[], 'no_baggage_info'],
        ];
    }

    #[Test]
    public function test_build_filter_meta_mixed_supplier_baggage_counts(): void
    {
        $controller = app(FlightController::class);
        $method = new ReflectionMethod(FlightController::class, 'buildFilterMeta');
        $method->setAccessible(true);

        $offers = [
            $this->airblueNullStructuredOffer(),
            [
                'supplier_provider' => 'sabre',
                'airline_code' => 'PK',
                'airline_name' => 'PIA',
                'baggage' => '23 kg',
                'stops' => 0,
                'final_customer_price' => 50000,
                'cabin' => 'economy',
                'segments' => [],
            ],
            [
                'supplier_provider' => 'iati',
                'airline_code' => 'ER',
                'airline_name' => 'Air Arabia',
                'baggage' => 'Cabin bag',
                'stops' => 0,
                'final_customer_price' => 40000,
                'cabin' => 'economy',
                'segments' => [],
            ],
            [
                'supplier_provider' => 'duffel',
                'airline_code' => 'BA',
                'airline_name' => 'British Airways',
                'baggage' => '',
                'stops' => 0,
                'final_customer_price' => 60000,
                'cabin' => 'economy',
                'segments' => [],
            ],
            [
                'supplier_provider' => 'pia_ndc',
                'airline_code' => 'PK',
                'airline_name' => 'PIA',
                'baggage' => [
                    'checked' => '30 kg',
                    'cabin' => null,
                    'summary' => '30 kg',
                ],
                'stops' => 0,
                'final_customer_price' => 45000,
                'cabin' => 'economy',
                'segments' => [],
            ],
        ];

        $airlineNameMap = [
            'PA' => 'airblue',
            'PK' => 'PIA',
            'ER' => 'Air Arabia',
            'BA' => 'British Airways',
        ];
        $meta = $method->invoke($controller, $offers, [], $airlineNameMap);
        $counts = [];
        foreach ($meta['baggage_options'] as $row) {
            $counts[(string) $row['value']] = (int) $row['count'];
        }

        $this->assertSame(2, $counts['checked_baggage'] ?? -1); // sabre + pia
        $this->assertSame(1, $counts['cabin_baggage'] ?? -1); // iati
        $this->assertSame(2, $counts['no_baggage_info'] ?? -1); // airblue + duffel
        $this->assertSame(5, array_sum($counts));
    }

    #[Test]
    public function test_non_consolidated_fare_option_baggage_buckets_use_resolver(): void
    {
        $method = new ReflectionMethod(ItineraryFareConsolidator::class, 'fareOptionBaggageBuckets');
        $method->setAccessible(true);

        $buckets = $method->invoke(null, $this->airblueNullStructuredOffer());
        $this->assertSame(['no_baggage_info'], $buckets);

        $checked = $method->invoke(null, [
            'baggage' => [
                'checked' => '20 kg',
                'cabin' => '7 kg',
                'summary' => '20 kg / 7 kg',
            ],
        ]);
        $this->assertSame(['checked_baggage'], $checked);
    }

    /**
     * @return array<string, mixed>
     */
    private function airblueNullStructuredOffer(): array
    {
        return [
            'supplier_provider' => 'airblue',
            'airline_code' => 'PA',
            'airline_name' => 'airblue',
            'baggage' => [
                'checked' => null,
                'cabin' => null,
                'summary' => null,
            ],
            'stops' => 0,
            'final_customer_price' => 26500,
            'cabin' => 'economy',
            'segments' => [],
        ];
    }
}
