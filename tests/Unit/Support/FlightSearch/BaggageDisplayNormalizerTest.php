<?php

namespace Tests\Unit\Support\FlightSearch;

use App\Support\FlightSearch\BaggageDisplayNormalizer;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BaggageDisplayNormalizerTest extends TestCase
{
    #[Test]
    public function test_normalizes_piece_and_kilo_tokens(): void
    {
        $this->assertSame('1 piece', BaggageDisplayNormalizer::normalizeToken('1PIECE'));
        $this->assertSame('20 kg', BaggageDisplayNormalizer::normalizeToken('20 KILO'));
        $this->assertSame('1 piece', BaggageDisplayNormalizer::normalizeLabel('1PIECE, 1PIECE, 1PIECE'));
    }

    #[Test]
    public function test_dedupes_distinct_comma_values(): void
    {
        $this->assertSame('0 kg, 20 kg, 30 kg', BaggageDisplayNormalizer::normalizeLabel('0 KILO, 20 KILO, 30 KILO'));
    }

    #[Test]
    public function test_labels_from_supplier_items_dedupes_repeated_entries(): void
    {
        $label = BaggageDisplayNormalizer::labelsFromSupplierItems([
            ['amount' => '1', 'unit' => 'PIECE'],
            ['amount' => '1', 'unit' => 'PIECE'],
            ['amount' => '1', 'unit' => 'PIECE'],
        ]);

        $this->assertSame('1 piece', $label);
    }

    #[Test]
    public function test_for_display_never_returns_null(): void
    {
        $this->assertSame(BaggageDisplayNormalizer::NOT_PROVIDED, BaggageDisplayNormalizer::forDisplay(null));
        $this->assertSame(BaggageDisplayNormalizer::NOT_PROVIDED, BaggageDisplayNormalizer::forDisplay(''));
        $this->assertSame('20 kg', BaggageDisplayNormalizer::forDisplay('20 KILO'));
        $this->assertSame('1 piece', BaggageDisplayNormalizer::forDisplay('1PIECE'));
    }

    #[Test]
    public function test_build_fare_summary_display_accepts_null_baggage(): void
    {
        $summary = FlightOfferDisplayPresenter::buildFareSummaryDisplay(
            ['supplier_provider' => 'iati', 'airline_code' => 'FZ'],
            'Flynas',
            null,
            null,
            null,
        );

        $this->assertIsArray($summary['baggage_lines']);
        $this->assertContains('Checked: '.BaggageDisplayNormalizer::NOT_PROVIDED, $summary['baggage_lines']);
        $this->assertContains('Carry-on: '.BaggageDisplayNormalizer::NOT_PROVIDED, $summary['baggage_lines']);
    }

    #[Test]
    public function test_build_fare_summary_display_keeps_normalized_baggage(): void
    {
        $summary = FlightOfferDisplayPresenter::buildFareSummaryDisplay(
            ['supplier_provider' => 'iati'],
            'Test Air',
            '20 kg',
            '1 piece',
            null,
        );

        $this->assertContains('Checked: 20 kg', $summary['baggage_lines']);
        $this->assertContains('Carry-on: 1 piece', $summary['baggage_lines']);
    }
}
