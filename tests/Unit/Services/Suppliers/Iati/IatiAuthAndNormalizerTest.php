<?php

namespace Tests\Unit\Services\Suppliers\Iati;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Iati\IatiAuthService;
use App\Services\Suppliers\Iati\IatiFareRulesService;
use App\Services\Suppliers\Iati\IatiResponseNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IatiAuthAndNormalizerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function test_auth_uses_auth_code_as_bearer_by_default(): void
    {
        Http::fake();

        $connection = new SupplierConnection([
            'id' => 99,
            'provider' => SupplierProvider::Iati,
            'environment' => SupplierEnvironment::Sandbox,
            'credentials' => ['auth_code' => 'direct-bearer-code', 'organization_id' => '187570'],
        ]);

        $token = app(IatiAuthService::class)->getBearerToken($connection);
        $this->assertSame('direct-bearer-code', $token);
        Http::assertNothingSent();
    }

    #[Test]
    public function test_auth_exchanges_token_when_secret_configured(): void
    {
        Http::fake([
            'https://testapi.iati.com/rest/auth/token' => Http::response(['access_token' => 'test-token-123'], 200),
        ]);

        $connection = new SupplierConnection([
            'id' => 99,
            'provider' => SupplierProvider::Iati,
            'environment' => SupplierEnvironment::Sandbox,
            'credentials' => ['auth_code' => 'code', 'secret' => 'secret', 'organization_id' => '187570'],
        ]);

        $token = app(IatiAuthService::class)->getBearerToken($connection);
        $this->assertSame('test-token-123', $token);

        $cached = app(IatiAuthService::class)->getBearerToken($connection);
        $this->assertSame('test-token-123', $cached);
        Http::assertSentCount(1);

        app(IatiAuthService::class)->getBearerToken($connection, true);
        Http::assertSentCount(2);
    }

    #[Test]
    public function test_normalizes_search_response_fixture(): void
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/iati/search_response_oneway.json')), true);
        $connection = new SupplierConnection([
            'id' => 1,
            'provider' => SupplierProvider::Iati,
            'environment' => SupplierEnvironment::Sandbox,
        ]);

        $offers = app(IatiResponseNormalizer::class)->normalizeSearchResponse($fixture, $connection, 'corr-1', 1, 0, 0);

        $this->assertCount(1, $offers);
        $this->assertSame('iati', $offers[0]->supplier_provider);
        $this->assertSame('LHE', $offers[0]->origin);
        $this->assertSame('DXB', $offers[0]->destination);
        $this->assertGreaterThan(0, $offers[0]->fare_breakdown->supplier_total);
        $context = $offers[0]->raw_payload['provider_context'] ?? [];
        $this->assertSame('dep-fare-key-abc', $context['departure_fare_key'] ?? null);
    }

    #[Test]
    public function test_single_fare_fixture_does_not_emit_branded_fares(): void
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/iati/search_response_oneway.json')), true);
        $connection = new SupplierConnection([
            'id' => 1,
            'provider' => SupplierProvider::Iati,
            'environment' => SupplierEnvironment::Sandbox,
        ]);

        $offers = app(IatiResponseNormalizer::class)->normalizeSearchResponse($fixture, $connection, 'corr-2', 1, 0, 0);

        $this->assertSame([], $offers[0]->branded_fares);
        $this->assertSame('Economy Saver', $offers[0]->fare_family);
    }

    #[Test]
    public function test_normalizes_fare_response_and_detects_price_change_fields(): void
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/iati/fare_response.json')), true);
        $fare = app(IatiResponseNormalizer::class)->normalizeFareResponse($fixture, [
            'departure_fare_key' => 'dep-fare-key-abc',
        ]);

        $this->assertSame('fare-detail-key-xyz', $fare['fare_detail_key']);
        $this->assertContains('offer-key-1', $fare['offer_keys']);
        $this->assertSame(355.0, $fare['total']);
    }

    #[Test]
    public function test_branded_fare_options_map_checked_baggage_by_fare_index(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/iati/search_response_branded_baggage.json')),
            true,
        );
        $connection = new SupplierConnection([
            'id' => 1,
            'provider' => SupplierProvider::Iati,
            'environment' => SupplierEnvironment::Sandbox,
        ]);

        $offers = app(IatiResponseNormalizer::class)->normalizeSearchResponse($fixture, $connection, 'corr-bag-1', 1, 0, 0);

        $this->assertCount(1, $offers);
        $branded = $offers[0]->branded_fares;
        $this->assertCount(3, $branded);
        $this->assertSame('0 kg', $branded[0]['check_in_summary'] ?? null);
        $this->assertSame('20 kg', $branded[1]['check_in_summary'] ?? null);
        $this->assertSame('30 kg', $branded[2]['check_in_summary'] ?? null);
        $this->assertSame('1 piece', $branded[0]['carry_on_summary'] ?? null);
        $this->assertSame('1 piece', $branded[1]['carry_on_summary'] ?? null);
        $this->assertSame('1 piece', $branded[2]['carry_on_summary'] ?? null);
        $this->assertSame('itinerary_leg_index', $branded[0]['checked_baggage_source'] ?? null);
    }

    #[Test]
    public function test_fare_level_baggages_override_itinerary_leg_aggregate(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/iati/search_response_branded_baggage.json')),
            true,
        );
        $fixture['result']['departure_flights'][0]['fares'][1]['baggages'] = [['amount' => '25', 'unit' => 'KILO']];
        $fixture['result']['departure_flights'][0]['fares'][1]['cabin_baggages'] = [['amount' => '2', 'unit' => 'PIECE']];

        $connection = new SupplierConnection([
            'id' => 1,
            'provider' => SupplierProvider::Iati,
            'environment' => SupplierEnvironment::Sandbox,
        ]);

        $offers = app(IatiResponseNormalizer::class)->normalizeSearchResponse($fixture, $connection, 'corr-bag-2', 1, 0, 0);
        $branded = $offers[0]->branded_fares;

        $this->assertSame('0 kg', $branded[0]['check_in_summary'] ?? null);
        $this->assertSame('25 kg', $branded[1]['check_in_summary'] ?? null);
        $this->assertSame('30 kg', $branded[2]['check_in_summary'] ?? null);
        $this->assertSame('2 pieces', $branded[1]['carry_on_summary'] ?? null);
        $this->assertSame('fare_baggages', $branded[1]['checked_baggage_source'] ?? null);
    }

    #[Test]
    public function test_fare_rules_service_parses_change_rules(): void
    {
        $rules = app(IatiFareRulesService::class)->normalizeRules([
            ['type' => 'CHANGE', 'before_departure_status' => 'PERMITTED'],
            ['type' => 'REFUND', 'before_departure_status' => 'NOT_PERMITTED'],
        ]);

        $this->assertContains('CHANGE before departure: PERMITTED', $rules);
        $this->assertContains('REFUND before departure: NOT_PERMITTED', $rules);
    }
}
