<?php

namespace App\Support\Time;

use App\Models\Agency;
use App\Models\User;
use DateTimeZone;
use Illuminate\Http\Request;

/**
 * Resolves display timezones for public visitors and logged-in operators.
 * Database timestamps remain UTC; this class is display-only.
 */
class DisplayTimezoneResolver
{
    public const DEFAULT_FALLBACK = 'Asia/Karachi';

    public const VISITOR_COOKIE = 'visitor_timezone';

    public static function safeTimezone(?string $timezone, string $fallback = self::DEFAULT_FALLBACK): string
    {
        $timezone = trim((string) ($timezone ?? ''));
        if ($timezone === '') {
            return $fallback;
        }

        try {
            new DateTimeZone($timezone);

            return $timezone;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    public function visitorTimezone(Request $request): string
    {
        $cookie = $request->cookie(self::VISITOR_COOKIE);

        return self::safeTimezone(is_string($cookie) ? $cookie : null);
    }

    public function userTimezone(?User $user, ?Agency $agency = null): string
    {
        if ($user !== null) {
            $metaTimezone = data_get($user->meta, 'timezone');
            if (filled($metaTimezone)) {
                return self::safeTimezone((string) $metaTimezone);
            }
        }

        $agency = $agency ?? $user?->currentAgency;
        if ($agency !== null) {
            if (filled($agency->timezone)) {
                return self::safeTimezone((string) $agency->timezone);
            }

            $agency->loadMissing('agencySetting');
            $settingTimezone = $agency->agencySetting?->timezone;
            if (filled($settingTimezone)) {
                return self::safeTimezone((string) $settingTimezone);
            }
        }

        return self::safeTimezone(null);
    }
}
