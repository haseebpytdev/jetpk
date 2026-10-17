<?php

namespace Tests\Unit\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\Support\AirBlue\AirBlueOfflineHttp;
use Tests\TestCase;

class AirBlueLiveCliGateTest extends TestCase
{
    use AirBlueOfflineHttp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAirBlueOfflineHttp();
        putenv('AIRBLUE_CLI_LIVE_ENABLED=false');
        $_ENV['AIRBLUE_CLI_LIVE_ENABLED'] = 'false';
        $_SERVER['AIRBLUE_CLI_LIVE_ENABLED'] = 'false';
    }

    public function test_test_search_fails_closed_without_live_gate(): void
    {
        $exit = Artisan::call('airblue:test-search', ['--from' => 'ISB', '--to' => 'DXB']);
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Blocked', Artisan::output());
    }

    public function test_retrieve_fails_closed_without_live_gate(): void
    {
        $exit = Artisan::call('airblue:retrieve', ['--booking' => '1']);
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Blocked', Artisan::output());
    }

    public function test_cancel_commit_fails_closed_without_live_gate(): void
    {
        $exit = Artisan::call('airblue:cancel-commit', ['booking' => '1']);
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Blocked', Artisan::output());
    }

    public function test_test_ticketing_fails_closed_without_live_gate(): void
    {
        $exit = Artisan::call('airblue:test-ticketing', ['booking' => '1']);
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Blocked', Artisan::output());
    }

    public function test_test_booking_dry_run_remains_offline(): void
    {
        $exit = Artisan::call('airblue:test-booking', ['booking' => '1', '--dry-run' => true]);
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Dry-run', Artisan::output());
    }
}
