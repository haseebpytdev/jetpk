<?php

namespace App\Support\Ai\Embed;

final class EmbedTenantCapability
{
    public const GENERAL_AI = 'general_ai';

    public const KNOWLEDGE = 'knowledge';

    public const LEAD_CAPTURE = 'lead_capture';

    public const FLIGHT_SEARCH = 'flight_search';

    public const BOOKING_LOOKUP = 'booking_lookup';

    public const SUPPORT_HANDOFF = 'support_handoff';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::GENERAL_AI,
            self::KNOWLEDGE,
            self::LEAD_CAPTURE,
            self::FLIGHT_SEARCH,
            self::BOOKING_LOOKUP,
            self::SUPPORT_HANDOFF,
        ];
    }
}
