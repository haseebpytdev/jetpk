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

        $url = app(AirlineBrandingService::class)->getLogoForCode('EK');
        $this->assertSame('/images/airline-generic.svg', $url);
    }

    #[Test]
    public function ignores_missing_db_logo_file_and_uses_generic(): void
    {
        Airline::query()->create([
            'iata_code' => 'XX',
            'name' => 'Missing Logo Airline',
            'is_active' => true,
            'logo_path' => 'travel-assets/airlines/logos/XX.png',
        ]);

        Config::set('ota.airline_logo_cache.download_on_miss', false);
        $url = app(AirlineBrandingService::class)->getLogoForCode('XX');
        $this->assertSame('/images/airline-generic.svg', $url);
    }
}
