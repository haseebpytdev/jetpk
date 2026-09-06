<?php

namespace Tests\Unit\Support\FlightSearch;

use App\Support\FlightSearch\SelectedOfferAuthority;
use Tests\TestCase;

class SelectedOfferAuthorityTest extends TestCase
{
    public function test_reuse_seconds_default_is_five_and_independent_of_display_freshness(): void
    {
        config([
            'ota.offer_freshness.refresh_due_seconds' => 300,
            'ota.offer_freshness.stale_after_seconds' => 600,
            'ota.selected_offer_authority.reuse_seconds' => 5,
        ]);

        $authority = app(SelectedOfferAuthority::class);
        $this->assertSame(5, $authority->reuseSeconds());
        $this->assertSame(300, (int) config('ota.offer_freshness.refresh_due_seconds'));
        $this->assertSame(600, (int) config('ota.offer_freshness.stale_after_seconds'));
    }

    public function test_may_reuse_within_five_seconds_with_matching_signature(): void
    {
        $authority = app(SelectedOfferAuthority::class);
        $offer = $this->offer();
        $ctx = $this->context();
        $fp = $authority->fingerprint($offer, $ctx);

        $this->assertTrue($authority->mayReuse(
            $offer,
            $ctx,
            now()->toIso8601String(),
            ['revalidation_status' => 'success'],
            false,
            $fp,
        ));
    }

    public function test_may_not_reuse_at_or_after_five_seconds(): void
    {
        $authority = app(SelectedOfferAuthority::class);
        $offer = $this->offer();
        $ctx = $this->context();
        $fp = $authority->fingerprint($offer, $ctx);

        $this->assertFalse($authority->mayReuse(
            $offer,
            $ctx,
            now()->subSeconds(5)->toIso8601String(),
            ['revalidation_status' => 'success'],
            false,
            $fp,
        ));
    }

    public function test_signature_mismatch_blocks_reuse(): void
    {
        $authority = app(SelectedOfferAuthority::class);
        $offer = $this->offer();
        $ctx = $this->context();
        $fp = $authority->fingerprint($offer, $ctx);

        $changed = $this->context(['adults' => 2]);
        $this->assertFalse($authority->mayReuse(
            $offer,
            $changed,
            now()->toIso8601String(),
            ['revalidation_status' => 'success'],
            false,
            $fp,
        ));
    }

    public function test_boolean_stamp_without_success_status_is_not_authority(): void
    {
        $authority = app(SelectedOfferAuthority::class);
        $offer = $this->offer();
        $offer['authoritative_bootstrap'] = true;

        $this->assertFalse($authority->mayReuse(
            $offer,
            $this->context(),
            now()->toIso8601String(),
            ['revalidation_status' => ''],
            false,
            null,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function offer(): array
    {
        return [
            'id' => 'offer-1',
            'offer_id' => 'offer-1',
            'supplier_provider' => 'sabre',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_at' => '2026-10-01T08:00:00+05:00',
            'total' => 100000,
            'currency' => 'PKR',
            'cabin' => 'economy',
            'segments' => [
                [
                    'marketing_carrier' => 'PK',
                    'flight_number' => '203',
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_at' => '2026-10-01T08:00:00+05:00',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function context(array $extra = []): array
    {
        return array_merge([
            'search_id' => 'search-1',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-10-01',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'economy',
            'currency' => 'PKR',
        ], $extra);
    }
}
