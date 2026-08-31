<?php

namespace App\Support\Onboarding;

/**
 * WIZARD_STATE_AUTHORITY — Laravel owns dashboard guided-tour completion state.
 *
 * Persistence: users.meta.dashboard_tours (JSON object keyed by tour id).
 * Each entry: {"status":"completed"|"skipped","at":"<ISO8601>"}.
 * Clients may only read/update the authenticated user's own map; user_id is never accepted.
 */
final class DashboardTourAuthority
{
    public const META_KEY = 'dashboard_tours';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_SKIPPED = 'skipped';

    public const CUSTOMER_TOUR = 'customer_dashboard_tour_v1';

    public const AGENT_TOUR = 'agent_dashboard_tour_v1';

    public const STAFF_TOUR = 'staff_dashboard_tour_v1';

    public const ADMIN_TOUR = 'admin_dashboard_tour_v1';

    public const ADMIN_API_SETTINGS_MINI = 'admin_api_settings_v1';

    /**
     * @return list<string>
     */
    public static function knownTourKeys(): array
    {
        return [
            self::CUSTOMER_TOUR,
            self::AGENT_TOUR,
            self::STAFF_TOUR,
            self::ADMIN_TOUR,
            self::ADMIN_API_SETTINGS_MINI,
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedStatuses(): array
    {
        return [self::STATUS_COMPLETED, self::STATUS_SKIPPED];
    }
}
