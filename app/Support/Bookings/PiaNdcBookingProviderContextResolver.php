<?php

namespace App\Support\Bookings;

use App\Models\Booking;
use App\Models\BookingHoldSession;

/**
 * Resolves PIA NDC OrderCreate provider_context from booking meta and hold sessions (R12G).
 */
final class PiaNdcBookingProviderContextResolver
{
    /**
     * @return array{context: array<string, mixed>, source: string}
     */
    public function resolve(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $validated = is_array($meta['validated_offer_snapshot'] ?? null) ? $meta['validated_offer_snapshot'] : [];
        $selected = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
        $selectedCtx = PiaNdcFareFamilyPolicy::extractProviderContextFromSelected($selected, $validated);
        if (PiaNdcFareFamilyPolicy::hasOrderCreateReadyContext($selectedCtx)) {
            $validatedCtx = is_array($validated['provider_context'] ?? null)
                ? $validated['provider_context']
                : $this->extractFromSnapshot($validated);
            if ($validatedCtx === [] || PiaNdcFareFamilyPolicy::providerContextsAlign($selectedCtx, $validatedCtx)) {
                return ['context' => $this->normalizeContext($selectedCtx, $validated), 'source' => 'booking.meta.selected_fare_family_option'];
            }
        }

        foreach ([
            'validated_offer_snapshot',
            'flight_offer_snapshot',
            'normalized_offer_snapshot',
        ] as $key) {
            $snapshot = is_array($meta[$key] ?? null) ? $meta[$key] : [];
            $context = $this->extractFromSnapshot($snapshot);
            if ($this->isComplete($context)) {
                return ['context' => $context, 'source' => 'booking.meta.'.$key];
            }
        }

        $hold = $this->resolveHoldSession($booking, $meta);
        if ($hold !== null) {
            $snapshot = is_array($hold->validated_offer_snapshot) ? $hold->validated_offer_snapshot : [];
            $context = $this->extractFromSnapshot($snapshot);
            if ($this->isComplete($context)) {
                return ['context' => $context, 'source' => 'booking_hold_sessions.'.$hold->id];
            }
        }

        if (is_array($meta['provider_context'] ?? null)) {
            $context = $this->normalizeContext($meta['provider_context'], []);
            if ($this->isComplete($context)) {
                return ['context' => $context, 'source' => 'booking.meta.provider_context'];
            }
        }

        return ['context' => [], 'source' => ''];
    }

    public function hasResolvableContext(Booking $booking): bool
    {
        return $this->resolve($booking)['context'] !== [];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function extractFromSnapshot(array $snapshot): array
    {
        if ($snapshot === []) {
            return [];
        }

        if (is_array($snapshot['provider_context'] ?? null)) {
            return $this->normalizeContext($snapshot['provider_context'], $snapshot);
        }

        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        if (is_array($raw['provider_context'] ?? null)) {
            return $this->normalizeContext($raw['provider_context'], $snapshot);
        }

        $rawReference = trim((string) ($snapshot['raw_reference'] ?? $raw['raw_reference'] ?? ''));
        if ($rawReference !== '') {
            return $this->normalizeContext(['offer_ref_id' => $rawReference], $snapshot);
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context, array $snapshot): array
    {
        if (trim((string) ($context['offer_ref_id'] ?? '')) === '') {
            $rawRef = trim((string) ($context['raw_reference'] ?? $snapshot['raw_reference'] ?? ''));
            if ($rawRef !== '') {
                $context['offer_ref_id'] = $rawRef;
            }
        }

        if (trim((string) ($context['offer_item_ref_id'] ?? '')) === '') {
            $items = is_array($context['offer_item_refs'] ?? null) ? $context['offer_item_refs'] : [];
            $first = is_array($items[0] ?? null) ? $items[0] : [];
            if (trim((string) ($first['offer_item_ref_id'] ?? '')) !== '') {
                $context['offer_item_ref_id'] = (string) $first['offer_item_ref_id'];
            }
        }

        if (trim((string) ($context['owner_code'] ?? '')) === '') {
            $owner = trim((string) ($snapshot['airline_code'] ?? 'PK'));
            $context['owner_code'] = $owner !== '' ? $owner : 'PK';
        }

        if (trim((string) ($context['shopping_response_ref_id'] ?? '')) === '') {
            $shoppingRef = trim((string) ($snapshot['shopping_response_ref_id'] ?? ''));
            if ($shoppingRef !== '') {
                $context['shopping_response_ref_id'] = $shoppingRef;
            }
        }

        if (trim((string) ($context['payment_time_limit'] ?? '')) === '') {
            $ttl = trim((string) ($snapshot['payment_time_limit'] ?? $snapshot['offer_expires_at'] ?? ''));
            if ($ttl !== '') {
                $context['payment_time_limit'] = $ttl;
            }
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function isComplete(array $context): bool
    {
        if (trim((string) ($context['offer_ref_id'] ?? '')) === '') {
            return false;
        }

        if (trim((string) ($context['offer_item_ref_id'] ?? '')) !== '') {
            return true;
        }

        $items = is_array($context['offer_item_refs'] ?? null) ? $context['offer_item_refs'] : [];

        return $items !== [];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveHoldSession(Booking $booking, array $meta): ?BookingHoldSession
    {
        $holdSessionId = (int) ($booking->hold_session_id ?? 0);
        if ($holdSessionId > 0) {
            $session = BookingHoldSession::query()->find($holdSessionId);
            if ($session !== null) {
                return $session;
            }
        }

        $byBooking = BookingHoldSession::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->first();
        if ($byBooking !== null) {
            return $byBooking;
        }

        $searchId = trim((string) ($meta['checkout_search_id'] ?? ''));
        $offerId = trim((string) ($meta['checkout_offer_id'] ?? ''));
        if ($searchId === '' || $offerId === '') {
            return null;
        }

        return BookingHoldSession::query()
            ->where('search_id', $searchId)
            ->where('offer_id', $offerId)
            ->orderByDesc('id')
            ->first();
    }
}
