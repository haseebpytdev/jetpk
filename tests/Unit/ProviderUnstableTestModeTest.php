<?php

namespace Tests\Unit;

use App\Support\ProviderUnstableTestMode;
use Tests\TestCase;

class ProviderUnstableTestModeTest extends TestCase
{
    public function test_staging_never_allows(): void
    {
        $this->assertFalse(ProviderUnstableTestMode::isCheckoutFallbackAllowed('staging'));
    }

    public function test_production_never_allows(): void
    {
        $this->assertFalse(ProviderUnstableTestMode::isCheckoutFallbackAllowed('production'));
    }

    public function test_testing_allows(): void
    {
        $this->assertTrue(ProviderUnstableTestMode::isCheckoutFallbackAllowed('testing'));
    }

    public function test_local_disallows_without_explicit_flag(): void
    {
        config(['ota.allow_provider_unstable_local' => false]);

        $this->assertFalse(ProviderUnstableTestMode::isCheckoutFallbackAllowed('local'));
    }

    public function test_local_allows_when_explicit_flag_enabled(): void
    {
        config(['ota.allow_provider_unstable_local' => true]);

        $this->assertTrue(ProviderUnstableTestMode::isCheckoutFallbackAllowed('local'));
    }
}
