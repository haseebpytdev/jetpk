<?php

namespace App\Support\Agencies;

use App\Models\Agency;
use Illuminate\Validation\ValidationException;

/**
 * Agency code prefixes stored in agencies.settings.code_prefix (no migration).
 */
final class AgencyPrefixService
{
    public const SETTINGS_KEY = 'code_prefix';

    public static function resolvePrefix(Agency $agency): string
    {
        $stored = self::storedPrefix($agency);
        if ($stored !== null) {
            return $stored;
        }

        return self::suggestPrefix($agency->name, (int) $agency->id);
    }

    public static function storedPrefix(Agency $agency): ?string
    {
        $settings = is_array($agency->settings) ? $agency->settings : [];
        $prefix = self::sanitizePrefix((string) ($settings[self::SETTINGS_KEY] ?? ''));

        return strlen($prefix) >= 2 ? $prefix : null;
    }

    public static function suggestPrefix(string $agencyName, ?int $agencyId = null): string
    {
        $words = collect(preg_split('/\s+/', trim($agencyName)) ?: [])
            ->filter(fn (string $word): bool => $word !== '')
            ->values();

        $initials = $words
            ->map(function (string $word): string {
                $alpha = preg_replace('/[^A-Za-z0-9]/', '', $word) ?? '';

                return strtoupper(substr($alpha, 0, 1));
            })
            ->filter()
            ->implode('');

        if ($initials === '') {
            $initials = 'AG';
        }

        $base = self::sanitizePrefix($initials);
        if (strlen($base) < 2) {
            $base = 'AG';
        }

        if (! self::isPrefixTaken($base, $agencyId)) {
            return $base;
        }

        if ($agencyId !== null && ! self::isPrefixTaken($base.$agencyId, $agencyId)) {
            return self::sanitizePrefix($base.$agencyId);
        }

        $suffix = 2;
        while ($suffix < 100) {
            $candidate = self::sanitizePrefix($base.$suffix);
            if (! self::isPrefixTaken($candidate, $agencyId)) {
                return $candidate;
            }
            $suffix++;
        }

        return self::sanitizePrefix($base.($agencyId ?? random_int(10, 99)));
    }

    public static function sanitizePrefix(string $prefix): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $prefix) ?? '');

        return substr($clean, 0, 4);
    }

    public static function validatePrefix(string $prefix, ?int $exceptAgencyId = null): void
    {
        $clean = self::sanitizePrefix($prefix);

        if (strlen($clean) < 2 || strlen($clean) > 4) {
            throw ValidationException::withMessages([
                'code_prefix' => 'Agency prefix must be 2 to 4 uppercase letters or numbers.',
            ]);
        }

        if ($clean !== strtoupper(trim($prefix))) {
            throw ValidationException::withMessages([
                'code_prefix' => 'Agency prefix must contain uppercase letters and numbers only.',
            ]);
        }

        if (self::isPrefixTaken($clean, $exceptAgencyId)) {
            throw ValidationException::withMessages([
                'code_prefix' => 'Agency prefix is already used by another agency.',
            ]);
        }
    }

    public static function isPrefixTaken(string $prefix, ?int $exceptAgencyId = null): bool
    {
        $clean = self::sanitizePrefix($prefix);
        if (strlen($clean) < 2) {
            return false;
        }

        return Agency::query()
            ->when($exceptAgencyId !== null, fn ($q) => $q->where('id', '!=', $exceptAgencyId))
            ->get(['id', 'settings'])
            ->contains(function (Agency $agency) use ($clean): bool {
                return self::storedPrefix($agency) === $clean;
            });
    }

    public static function savePrefix(Agency $agency, string $prefix): Agency
    {
        self::validatePrefix($prefix, (int) $agency->id);

        $settings = is_array($agency->settings) ? $agency->settings : [];
        $settings[self::SETTINGS_KEY] = self::sanitizePrefix($prefix);
        $agency->forceFill(['settings' => $settings])->save();

        return $agency->fresh();
    }

    public static function canAgentSetPrefix(Agency $agency): bool
    {
        return self::storedPrefix($agency) === null;
    }

    public static function canPlatformAdminEditPrefix(Agency $agency): bool
    {
        return true;
    }
}
