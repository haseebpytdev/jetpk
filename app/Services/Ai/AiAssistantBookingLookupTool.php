<?php

namespace App\Services\Ai;

use App\Models\AiConversation;
use App\Models\Booking;
use App\Services\Customer\GuestBookingAccessService;
use App\Support\Ai\AiAssistantBookingChatPresenter;

/**
 * Read-only in-chat booking lookup via authoritative GuestBookingAccessService.
 */
final class AiAssistantBookingLookupTool
{
    public function __construct(
        private readonly GuestBookingAccessService $guestAccess,
        private readonly AiAssistantBookingChatPresenter $presenter,
    ) {}

    /**
     * @return array{
     *   ok: bool,
     *   found: bool,
     *   booking?: array<string, mixed>,
     *   message: string,
     *   lookup_url?: string
     * }
     */
    public function lookup(string $reference, ?string $email, ?string $phone = null): array
    {
        $reference = trim($reference);
        $email = is_string($email) ? strtolower(trim($email)) : null;
        $phone = is_string($phone) ? trim($phone) : null;

        if ($reference === '' || ($email === null && $phone === null)) {
            return [
                'ok' => false,
                'found' => false,
                'message' => 'I need your booking reference and the email used when you booked.',
            ];
        }

        $booking = $this->guestAccess->findBookingForLookup($reference, $email, $phone);
        if ($booking === null) {
            return [
                'ok' => true,
                'found' => false,
                'message' => 'I could not find a booking matching that reference and email. Please double-check both details.',
            ];
        }

        $payload = $this->presenter->present($booking);
        $summary = $this->presenter->summarizeForChat($payload);

        return [
            'ok' => true,
            'found' => true,
            'booking' => $payload,
            'message' => $summary,
            'lookup_url' => '/lookup-booking',
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function patchState(array $state, string $message): array
    {
        if (preg_match('/\b(reference|ref|pnr)\s*(is|:)?\s*([A-Z0-9]{5,12})\b/i', $message, $m) === 1) {
            $state['booking_reference'] = strtoupper($m[3]);
        } elseif (preg_match('/\b([A-Z0-9]{5,12})\b/u', $message, $m) === 1
            && preg_match('/\b(reference|ref|pnr|booking)\b/i', $message) === 1) {
            $state['booking_reference'] = strtoupper($m[1]);
        }

        if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $message, $m) === 1) {
            $state['booking_email'] = strtolower($m[0]);
        }

        return $state;
    }
}
