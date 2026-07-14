<?php

namespace App\Services\Suppliers\Sabre\Ticketing;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Safe audit logging for Sabre GDS ticketing (no secrets, tokens, or raw payloads).
 */
final class SabreGdsTicketingAuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $event, Booking $booking, ?User $actor = null, array $context = []): void
    {
        Log::channel('stack')->info($event, array_merge([
            'booking_id' => $booking->id,
            'agency_id' => $booking->agency_id,
            'actor_id' => $actor?->id,
            'provider' => 'sabre',
        ], $context));
    }
}
