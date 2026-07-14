<?php

namespace App\Support\Audits;

/**
 * Standard read-only safety lines for F8 booking-flow smoke CLI output.
 */
final class BookingFlowSmokeSafetyOutput
{
    /**
     * @return list<string>
     */
    public static function readOnlyBanner(): array
    {
        return [
            'Classification: READ-ONLY',
            'live_supplier_call_attempted=false',
            'booking_created=false',
            'ticketing_attempted=false',
            'auto_pnr_attempted=false',
            'cancellation_attempted=false',
        ];
    }
}
