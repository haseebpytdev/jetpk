<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

class AirBlueNoGlobalKillSwitchTest extends AirBlueOfflineTestCase
{
    public function test_airblue_runtime_has_no_global_transport_kill_switch_dependency(): void
    {
        $paths = [
            app_path('Services/Suppliers/AirBlue/AirBlueClient.php'),
            app_path('Services/Suppliers/AirBlue/AirBlueTicketingService.php'),
            app_path('Console/Commands/Concerns/GuardsAirBlueLiveNetwork.php'),
            config_path('suppliers.php'),
        ];

        foreach ($paths as $path) {
            $source = (string) file_get_contents($path);

            $this->assertStringNotContainsString('AIRBLUE_EXTERNAL_CALLS_ENABLED', $source, $path);
            $this->assertStringNotContainsString('AIRBLUE_CLI_LIVE_ENABLED', $source, $path);
            $this->assertStringNotContainsString("suppliers.airblue.external_calls_enabled", $source, $path);
        }

        $this->assertArrayNotHasKey('external_calls_enabled', (array) config('suppliers.airblue', []));
    }
}
