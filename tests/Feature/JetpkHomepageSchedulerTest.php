<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class JetpkHomepageSchedulerTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_lists_exactly_one_homepage_route_fare_refresh_registration(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        preg_match_all('/jetpk:homepage-route-fares-refresh/', $output, $matches);
        $this->assertCount(1, $matches[0], 'Expected exactly one scheduler registration for jetpk:homepage-route-fares-refresh');

        preg_match('/^\s*(.+?)\s+php artisan jetpk:homepage-route-fares-refresh/m', $output, $lineMatch);
        $cron = preg_replace('/\s+/', ' ', trim($lineMatch[1] ?? ''));
        $this->assertSame('30 0 * * *', $cron, 'Expected dailyAt(00:30) cron expression');
        $this->assertStringContainsString('php artisan jetpk:homepage-route-fares-refresh', $output);
    }
}
