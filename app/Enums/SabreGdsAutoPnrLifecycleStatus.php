<?php

namespace App\Enums;

/**
 * Sabre GDS auto-PNR lifecycle checkpoints (offer refresh → PNR → itinerary sync → ticketing pending).
 */
enum SabreGdsAutoPnrLifecycleStatus: string
{
    case OfferRefreshed = 'offer_refreshed';
    case PnrCreated = 'pnr_created';
    case ItinerarySynced = 'itinerary_synced';
    case TicketingPending = 'ticketing_pending';

    public function label(): string
    {
        return match ($this) {
            self::OfferRefreshed => 'Offer refreshed',
            self::PnrCreated => 'PNR created',
            self::ItinerarySynced => 'Itinerary synced',
            self::TicketingPending => 'Ticketing pending',
        };
    }
}
