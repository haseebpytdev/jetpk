<?php

namespace Tests\Support\Sabre;

use App\Data\FlightSearchRequestData;
use App\Data\NormalizedFlightOfferData;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreFlightSearchNormalizer;
use PHPUnit\Framework\Assert;

/**
 * Phase 18D: shared normalizer matrix assertions for fake-HTTP BFM fixtures.
 */
final class SabreGdsShoppingNormalizationMatrixSupport
{
    /**
     * @param  array<string, mixed>  $criteria
     * @return list<NormalizedFlightOfferData>
     */
    public static function normalizeFixture(string $fixturePath, array $criteria, ?SupplierConnection $connection = null): array
    {
        $fixture = json_decode((string) file_get_contents(base_path($fixturePath)), true);
        if (! is_array($fixture)) {
            throw new \InvalidArgumentException('Fixture must decode to array: '.$fixturePath);
        }

        $connection ??= SupplierConnection::factory()->create();
        $searchRequest = FlightSearchRequestData::fromArray($criteria);

        $offers = app(SabreFlightSearchNormalizer::class)->normalize($fixture, $connection, $searchRequest);

        return array_values(array_filter(
            $offers,
            static fn ($offer): bool => $offer instanceof NormalizedFlightOfferData,
        ));
    }

    /**
     * @param  list<NormalizedFlightOfferData>  $offers
     */
    public static function assertNoDuplicateOfferIds(array $offers, string $label = ''): void
    {
        $ids = array_map(static fn (NormalizedFlightOfferData $o): string => $o->offer_id, $offers);
        Assert::assertSame(count($ids), count(array_unique($ids)), $label.' duplicate offer_id values');
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     */
    public static function assertSegmentChain(array $segments, string $label = ''): void
    {
        $prevDest = null;
        foreach ($segments as $idx => $segment) {
            $origin = strtoupper(trim((string) ($segment['origin'] ?? '')));
            $dest = strtoupper(trim((string) ($segment['destination'] ?? '')));
            Assert::assertNotSame('', $origin, $label.' segment '.$idx.' origin');
            Assert::assertNotSame('', $dest, $label.' segment '.$idx.' destination');
            if ($prevDest !== null) {
                Assert::assertSame($prevDest, $origin, $label.' segment continuity at '.$idx);
            }
            $prevDest = $dest;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     */
    public static function assertSegmentsHaveRequiredFields(array $segments, string $label = ''): void
    {
        foreach ($segments as $idx => $segment) {
            $marketing = strtoupper(trim((string) ($segment['airline_code'] ?? $segment['marketing_carrier'] ?? '')));
            Assert::assertMatchesRegularExpression('/^[A-Z0-9]{2}$/', $marketing, $label.' segment '.$idx.' marketing carrier');
            Assert::assertNotSame('', trim((string) ($segment['flight_number'] ?? '')), $label.' segment '.$idx.' flight number');
            Assert::assertNotSame('', trim((string) ($segment['departure_at'] ?? '')), $label.' segment '.$idx.' departure_at');
            Assert::assertNotSame('', trim((string) ($segment['arrival_at'] ?? '')), $label.' segment '.$idx.' arrival_at');
        }
    }

    /**
     * @param  list<NormalizedFlightOfferData>  $runs
     */
    public static function assertSignatureStableAcrossRuns(array $runs, string $label = ''): void
    {
        Assert::assertGreaterThanOrEqual(2, count($runs), $label.' requires at least two normalization runs');
        $first = $runs[0]->offer_id;
        foreach ($runs as $run) {
            Assert::assertSame($first, $run->offer_id, $label.' offer_id must be deterministic');
        }
    }
}
