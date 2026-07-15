<?php

namespace Tests\Unit\FlightSearch;

use App\Support\FlightSearch\DirectFlightsOfferFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DirectFlightsOfferFilterTest extends TestCase
{
    private DirectFlightsOfferFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new DirectFlightsOfferFilter;
    }

    public function test_is_enabled_reads_direct_only_criteria_flag(): void
    {
        $this->assertFalse($this->filter->isEnabled([]));
        $this->assertFalse($this->filter->isEnabled(['direct_only' => false]));
        $this->assertTrue($this->filter->isEnabled(['direct_only' => true]));
        $this->assertTrue($this->filter->isEnabled(['direct_only' => '1']));
    }

    #[DataProvider('directOfferCases')]
    public function test_is_direct_offer(array $offer, bool $expected): void
    {
        $this->assertSame($expected, $this->filter->isDirectOffer($offer));
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: bool}>
     */
    public static function directOfferCases(): iterable
    {
        yield 'stops zero' => [['stops' => 0], true];
        yield 'stops one' => [['stops' => 1], false];
        yield 'single segment' => [['segments' => [['direction' => 'outbound']]], true];
        yield 'two outbound segments' => [['segments' => [
            ['direction' => 'outbound'],
            ['direction' => 'outbound'],
        ]], false];
        yield 'round trip two segments each way' => [[
            'trip_type' => 'round_trip',
            'segments' => [
                ['direction' => 'outbound'],
                ['direction' => 'outbound'],
                ['direction' => 'return'],
            ],
        ], false];
    }

    public function test_filter_display_offers_keeps_only_direct_rows(): void
    {
        $result = $this->filter->filterDisplayOffers([
            ['offer_id' => 'a', 'stops' => 0],
            ['offer_id' => 'b', 'stops' => 1],
            ['offer_id' => 'c', 'stops' => 0],
        ]);

        $this->assertSame(['a', 'c'], array_column($result['offers'], 'offer_id'));
        $this->assertSame(3, $result['diagnostics']['offers_before_direct_filter']);
        $this->assertSame(2, $result['diagnostics']['offers_after_direct_filter']);
        $this->assertSame(1, $result['diagnostics']['direct_filter_dropped_count']);
    }
}
