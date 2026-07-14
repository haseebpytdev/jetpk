<?php

namespace Tests\Unit;

use App\Models\Agency;
use App\Models\AgencySetting;
use App\Models\User;
use App\Support\Time\DisplayTimezoneResolver;
use App\Support\Time\LocalTimeDisplay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DisplayTimezoneResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_safe_timezone_falls_back_for_invalid_values(): void
    {
        $this->assertSame('Asia/Karachi', DisplayTimezoneResolver::safeTimezone(null));
        $this->assertSame('Asia/Karachi', DisplayTimezoneResolver::safeTimezone('Not/AZone'));
        $this->assertSame('Europe/London', DisplayTimezoneResolver::safeTimezone('Europe/London'));
    }

    public function test_visitor_timezone_uses_cookie_then_fallback(): void
    {
        $resolver = new DisplayTimezoneResolver;

        $request = Request::create('/', 'GET', [], [
            DisplayTimezoneResolver::VISITOR_COOKIE => 'America/New_York',
        ]);

        $this->assertSame('America/New_York', $resolver->visitorTimezone($request));
        $this->assertSame('Asia/Karachi', $resolver->visitorTimezone(Request::create('/')));
    }

    public function test_user_timezone_prefers_meta_then_agency_then_fallback(): void
    {
        $resolver = new DisplayTimezoneResolver;

        $agency = Agency::factory()->create(['timezone' => 'Europe/Berlin']);
        AgencySetting::query()->create([
            'agency_id' => $agency->id,
            'timezone' => 'America/Chicago',
        ]);

        $user = User::factory()->create([
            'current_agency_id' => $agency->id,
            'meta' => ['timezone' => 'Asia/Dubai'],
        ]);

        $this->assertSame('Asia/Dubai', $resolver->userTimezone($user, $agency));

        $userWithoutMeta = User::factory()->create([
            'current_agency_id' => $agency->id,
            'meta' => [],
        ]);

        $this->assertSame('Europe/Berlin', $resolver->userTimezone($userWithoutMeta, $agency));

        $agencyWithoutColumnTz = Agency::factory()->create(['timezone' => '']);
        AgencySetting::query()->create([
            'agency_id' => $agencyWithoutColumnTz->id,
            'timezone' => 'Pacific/Auckland',
        ]);

        $this->assertSame(
            'Pacific/Auckland',
            $resolver->userTimezone(null, $agencyWithoutColumnTz->fresh(['agencySetting']))
        );
    }

    public function test_local_time_display_converts_from_utc_storage(): void
    {
        $display = new LocalTimeDisplay;
        $utc = Carbon::parse('2026-06-05 18:42:00', 'UTC');

        $formatted = $display->format($utc, 'Asia/Karachi', true);

        $this->assertNotNull($formatted);
        $this->assertStringContainsString('PKT', $formatted['label']);
        $this->assertStringContainsString('UTC:', $formatted['utc_title']);
        $this->assertSame('11:42 PM PKT', $display->formatExpiryHint($utc, 'Asia/Karachi'));
    }
}
