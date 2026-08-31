<?php

namespace Tests\Feature\Share;

use App\Models\PublicShareLink;
use App\Services\Share\PublicShareLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicShareLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_resolve_flight_short_link(): void
    {
        $service = app(PublicShareLinkService::class);
        $link = $service->createFlightLink([
            'origin' => 'ISB',
            'destination' => 'DXB',
            'depart_date' => '2026-09-15',
            'trip_type' => 'one_way',
            'adults' => 1,
            'cabin' => 'economy',
            'display_fare' => 100515,
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
        ], 'test');

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6,12}$/', $link->code);
        $this->assertFalse($link->isExpired());

        $response = $this->get('/f/'.$link->code);
        $response->assertRedirect();
        $this->assertStringContainsString('/flights/results', $response->headers->get('Location') ?? '');
        $this->assertStringContainsString('from=ISB', $response->headers->get('Location') ?? '');
    }

    public function test_unknown_and_malformed_codes(): void
    {
        $this->get('/f/NOTEXIST1')->assertStatus(404);
        $this->get('/f/bad!')->assertStatus(404);
    }

    public function test_expired_link_shows_recovery_page(): void
    {
        $link = PublicShareLink::query()->create([
            'code' => 'EXPIRED1',
            'link_type' => 'flight_fare',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-20',
            'trip_type' => 'one_way',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'economy',
            'display_currency' => 'PKR',
            'display_fare' => 90000,
            'expires_at' => now()->subMinute(),
        ]);

        $this->get('/f/'.$link->code)
            ->assertOk()
            ->assertSee('This fare reference has expired');
    }

    public function test_create_api_returns_short_url(): void
    {
        $response = $this->postJson('/api/public/share/flight', [
            'origin' => 'ISB',
            'destination' => 'JED',
            'depart_date' => '2026-10-01',
            'adults' => 2,
            'display_fare' => 150000,
        ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertStringContainsString('/f/', (string) $response->json('url'));
    }

    public function test_create_and_resolve_group_short_link_landing(): void
    {
        $response = $this->postJson('/api/public/share/group', [
            'package_id' => 'grp-demo-1',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => '2026-11-01',
            'display_fare' => 185000,
            'title' => 'LHE–JED group seats',
            'seats_available' => 12,
        ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $code = (string) $response->json('code');
        $this->assertStringContainsString('/g/', (string) $response->json('url'));

        $this->get('/g/'.$code)
            ->assertOk()
            ->assertSee('LHE–JED group seats')
            ->assertSee('View & continue')
            ->assertDontSee('supplier')
            ->assertDontSee('cost');
    }

    public function test_expired_group_link_shows_recovery_page(): void
    {
        PublicShareLink::query()->create([
            'code' => 'GEXPIRED1',
            'link_type' => 'group_offer',
            'origin' => 'ISB',
            'destination' => 'DXB',
            'depart_date' => '2026-09-20',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'display_currency' => 'PKR',
            'display_fare' => 90000,
            'expires_at' => now()->subMinute(),
            'payload' => ['package_id' => 'grp-x'],
        ]);

        $this->get('/g/GEXPIRED1')
            ->assertOk()
            ->assertSee('This group reference has expired');
    }
}
