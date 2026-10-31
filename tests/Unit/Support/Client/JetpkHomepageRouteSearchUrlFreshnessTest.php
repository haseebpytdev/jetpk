<?php

namespace Tests\Unit\Support\Client;

use App\Support\Client\JetpkHomepageSectionData;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

/**
 * JP-AUDIT-CLOSURE-02 / CMS stale fare:
 * homepage route search CTAs must never expose a past departure date.
 */
class JetpkHomepageRouteSearchUrlFreshnessTest extends TestCase
{
    public function test_past_pinned_cta_url_is_rebuilt_with_future_depart(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31', 'Asia/Karachi'));

        $service = app(JetpkHomepageSectionData::class);
        $method = new ReflectionMethod(JetpkHomepageSectionData::class, 'routeSearchUrl');
        $method->setAccessible(true);

        $url = $method->invoke($service, [
            'from' => 'LHE',
            'to' => 'DXB',
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'cta_url' => 'https://jetpakistan.pk/flights/results?from=LHE&to=DXB&depart=2026-09-22&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0',
        ]);

        $this->assertStringContainsString('depart=', $url);
        $this->assertStringNotContainsString('depart=2026-09-22', $url);
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $q);
        $this->assertArrayHasKey('depart', $q);
        $this->assertTrue(
            \Carbon\Carbon::parse((string) $q['depart'], 'Asia/Karachi')->startOfDay()
                ->greaterThanOrEqualTo(\Carbon\Carbon::parse('2026-10-31', 'Asia/Karachi')->startOfDay()),
            'rebuilt depart must be today or future'
        );
    }

    public function test_future_pinned_cta_url_is_preserved(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31', 'Asia/Karachi'));

        $service = app(JetpkHomepageSectionData::class);
        $method = new ReflectionMethod(JetpkHomepageSectionData::class, 'routeSearchUrl');
        $method->setAccessible(true);

        $pinned = 'https://jetpakistan.pk/flights/results?from=LHE&to=DXB&depart=2026-11-20&trip_type=one_way&cabin=economy&adults=1';
        $url = $method->invoke($service, [
            'from' => 'LHE',
            'to' => 'DXB',
            'cta_url' => $pinned,
        ]);

        $this->assertSame($pinned, $url);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
