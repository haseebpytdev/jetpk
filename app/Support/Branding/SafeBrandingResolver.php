<?php

namespace App\Support\Branding;

use App\Models\Agency;
use App\Services\Agencies\AgencyBrandingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SafeBrandingResolver
{
    /**
     * @return array<string, mixed>
     */
    public static function resolveForPublic(?AgencyBrandingService $brandingService = null): array
    {
        $fallback = self::fallbackPayload();

        try {
            if (! Schema::hasTable('agencies')) {
                return $fallback;
            }

            $slug = (string) config('ota.default_agency_slug', '');
            if ($slug === '') {
                return $fallback;
            }

            $agency = Agency::query()->where('slug', $slug)->first();
            if ($agency === null) {
                return $fallback;
            }

            $service = $brandingService ?? app(AgencyBrandingService::class);
            $payload = $service->publicBrandingPayload($agency);

            return array_merge($fallback, $payload, ['is_fallback' => false]);
        } catch (Throwable $e) {
            Log::warning('Safe branding fallback engaged.', [
                'error' => class_basename($e),
                'message' => app()->environment('production') ? 'suppressed' : $e->getMessage(),
            ]);

            return $fallback;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function fallbackPayload(): array
    {
        if (function_exists('ota_single_client_root_slug') && ota_single_client_root_slug() === 'jetpk') {
            return [
                'has_db_settings' => false,
                'settings' => null,
                'sections' => collect(),
                'public_media_base' => '/storage',
                'is_fallback' => true,
                'fallback_brand' => [
                    'name' => 'JetPakistan',
                    'tagline' => 'Book flights, manage bookings, and travel with confidence.',
                    'support_email' => 'support@jetpakistan.com',
                    'support_phone' => '',
                ],
            ];
        }

        return [
            'has_db_settings' => false,
            'settings' => null,
            'sections' => collect(),
            'public_media_base' => '/storage',
            'is_fallback' => true,
            'fallback_brand' => [
                'name' => 'Hayat Travel Solutions',
                'tagline' => 'Reliable travel support for agencies and customers.',
                'support_email' => 'support@hayattravelsolutions.com',
                'support_phone' => '+92 300 0000000',
            ],
        ];
    }
}
