<?php

namespace App\Support\Qa;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Ownership boundaries for JP-DASH-FINAL-11 QA provisioning (fail closed).
 */
final class JetpkDashboardFinal11QaScope
{
    public const QA_RUN_ID = 'JP-FINAL-11';

    public const USERNAME_PREFIX = 'jp-final-11-qa-';

    public const QA_AGENCY_A_SLUG = 'jetpk-production-qa';

    public const QA_AGENCY_B_SLUG = 'jetpk-production-qa-b';

    public const LEGACY_AGENCY_SLUG = 'jp-dash-03-qa-agency';

    public static function isOwnedUsername(string $username): bool
    {
        return str_starts_with($username, self::USERNAME_PREFIX);
    }

    public static function isOwnedUser(User $user): bool
    {
        if (! self::isOwnedUsername((string) $user->username)) {
            return false;
        }

        $meta = is_array($user->meta) ? $user->meta : [];

        return (bool) ($meta['jp_final_11_qa'] ?? false)
            || (string) ($meta['qa_run_id'] ?? '') === self::QA_RUN_ID;
    }

    public static function agencyIsSafeToMutate(Agency $agency, string $expectedSlug): bool
    {
        if ($agency->slug !== $expectedSlug) {
            return false;
        }

        $settings = is_array($agency->settings) ? $agency->settings : [];

        if ($expectedSlug === self::QA_AGENCY_A_SLUG) {
            return (bool) data_get($settings, 'qa_only')
                || (bool) data_get($settings, 'jp_dash_03_qa')
                || (bool) data_get($settings, 'jp_final_11_qa')
                || (string) data_get($settings, 'qa_run_id') === self::QA_RUN_ID
                || (string) data_get($settings, 'purpose') === 'production_operational_certification';
        }

        if ($expectedSlug === self::QA_AGENCY_B_SLUG) {
            return (bool) data_get($settings, 'qa_only')
                || (bool) data_get($settings, 'jp_final_11_qa')
                || (string) data_get($settings, 'qa_run_id') === self::QA_RUN_ID
                || (string) data_get($settings, 'purpose') === 'jp_dash_final_11_certification';
        }

        if ($expectedSlug === self::LEGACY_AGENCY_SLUG) {
            return (bool) data_get($settings, 'jp_dash_03_qa')
                || (bool) data_get($settings, 'qa_only')
                || (string) data_get($settings, 'purpose') === 'jp_dash_03_qa_agency';
        }

        return false;
    }

    public static function userMatchesDefinition(User $user, AccountType $expectedType, string $expectedMarker): bool
    {
        if ($user->username !== $expectedMarker) {
            return false;
        }

        if ($user->account_type !== $expectedType) {
            return false;
        }

        return self::isOwnedUser($user);
    }

    public static function hasPositiveOwnershipMeta(User $user): bool
    {
        return self::isOwnedUser($user);
    }

    /**
     * One-time reconcile bootstrap credential: high-entropy hash only (never returns plaintext).
     */
    public static function bootstrapPasswordHash(): string
    {
        return Hash::make(Str::password(64));
    }
}
