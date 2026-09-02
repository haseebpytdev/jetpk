<?php

namespace Tests\Unit\Services\TravelData;

use App\Models\Airline;
use App\Services\TravelData\AirlineBrandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AirlineBrandingServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function resolves_db_logo_path_before_cache(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('travel-assets/airlines/logos/PK.png', 'png-bytes');

        Airline::query()->create([
            'iata_code' => 'PK',
            'name' => 'Pakistan International Airlines',
            'is_active' => true,
            'logo_path' => 'travel-assets/airlines/logos/PK.png',
        ]);

        $url = app(AirlineBrandingService::class)->getLogoForCode('PK');
        $this->assertIsString($url);
        $this->assertStringContainsString('/storage/travel-assets/airlines/logos/PK.png', $url);
        $this->assertStringNotContainsString('haseebasif.com', $url);
    }

    #[Test]
    public function falls_back_to_generic_without_external_download_in_tests(): void
    {
        Config::set('ota.airline_logo_cache.download_on_miss', false);

        $url = app(AirlineBrandingService::class)->getLogoForCode('ZZUNK');
        $this->assertSame('/images/airline-generic.svg', $url);
    }

    #[Test]
    public function prefers_travel_assets_master_before_cdn_for_pf(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('travel-assets/airlines/logos/PF.png', 'airsial-bytes');
        Config::set('ota.airline_logo_cache.download_on_miss', true);

        $url = app(AirlineBrandingService::class)->getLogoForCode('PF');
        $this->assertStringContainsString('/storage/travel-assets/airlines/logos/PF.png', (string) $url);
    }

    #[Test]
    public function blocks_iata_only_cdn_download_for_pf_and_9p(): void
    {
        $cache = app(\App\Services\TravelData\AirlineLogoCacheService::class);
        $this->assertTrue($cache->isIataOnlyDownloadBlocked('PF'));
        $this->assertTrue($cache->isIataOnlyDownloadBlocked('9P'));
    }
}
