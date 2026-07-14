<?php

namespace Tests\Unit\Support\FlightSearch;

use App\Support\FlightSearch\BaggageDisplayNormalizer;
use App\Support\FlightSearch\OfferBaggageResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OfferBaggageResolverTest extends TestCase
{
    #[Test]
    public function test_resolves_offer_baggage_from_structured_fields(): void
    {
        $resolved = OfferBaggageResolver::resolveFromOffer([
            'baggage' => ['checked' => '20 KILO', 'cabin' => '1PIECE'],
        ]);

        $this->assertSame('20 kg', $resolved['checked']);
        $this->assertSame('1 piece', $resolved['cabin']);
    }

    #[Test]
    public function test_splits_combined_summary_into_checked_and_cabin(): void
    {
        $split = BaggageDisplayNormalizer::splitCombinedSummary('CHECKED UP TO 25 KG · CABIN UP TO 7 KG');

        $this->assertSame('25 kg', $split['checked']);
        $this->assertSame('7 kg', $split['cabin']);
    }

    #[Test]
    public function test_enriches_fare_option_row_from_summary_only(): void
    {
        $row = OfferBaggageResolver::enrichFareOptionRow([
            'name' => 'Economy',
            'baggage_summary' => '20 kg / 7 kg',
        ]);

        $this->assertSame('20 kg', $row['check_in_summary'] ?? null);
        $this->assertSame('7 kg', $row['carry_on_summary'] ?? null);
    }
}
