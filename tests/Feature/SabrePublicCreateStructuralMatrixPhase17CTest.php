<?php

namespace Tests\Feature;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreFlightSearchNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 17C: structural itinerary integrity via sanitized BFM fixtures (no live HTTP).
 */
class SabrePublicCreateStructuralMatrixPhase17CTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('structuralFixtureProvider')]
    public function test_normalizer_produces_ordered_segments_for_fixture(string $fixtureFile, int $expectedSegmentCount, string $firstOrigin, string $lastDestination): void
    {
        $fixture = json_decode((string) file_get_contents(base_path($fixtureFile)), true);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => $firstOrigin,
            'destination' => $lastDestination,
            'depart_date' => '2026-09-01',
        ]);

        $offers = app(SabreFlightSearchNormalizer::class)->normalize($fixture, $connection, $searchRequest);
        $this->assertNotEmpty($offers, $fixtureFile);
        $segments = $offers[0]->segments;
        $this->assertCount($expectedSegmentCount, $segments);
        $this->assertSame($firstOrigin, $segments[0]['origin'] ?? null);
        $this->assertSame($lastDestination, $segments[array_key_last($segments)]['destination'] ?? null);
    }

    public function test_disconnected_descriptor_fixture_rejects_before_offer_normalization(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_explicit_ids_unresolved_schedule_refs.json')),
            true,
        );
        $connection = SupplierConnection::factory()->create(['provider' => SupplierProvider::Sabre]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DOH',
            'depart_date' => '2026-09-01',
        ]);

        $offers = app(SabreFlightSearchNormalizer::class)->normalize($fixture, $connection, $searchRequest);
        $this->assertCount(0, $offers);
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: string, 3: string}>
     */
    public static function structuralFixtureProvider(): array
    {
        return [
            'descriptor_misaligned_order' => [
                'tests/Fixtures/sabre_bfm_v4_descriptor_ids_misaligned_with_array_order.json',
                2,
                'LHE',
                'DOH',
            ],
            'standard_search_response' => [
                'tests/Fixtures/sabre_search_response.json',
                1,
                'LHE',
                'DXB',
            ],
        ];
    }
}
