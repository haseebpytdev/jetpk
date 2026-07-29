<?php

namespace App\Support\GroupTicketing;

use App\Enums\GroupBookingStatus;
use App\Models\GroupBooking;
use App\Models\GroupInventory;
use App\Services\GroupTicketing\GroupBookingRestrictionService;
use App\Services\GroupTicketing\GroupReservationService;
use App\Support\Geo\CountryList;
use Illuminate\Support\Collection;

/**
 * Structured JSON payloads for Next.js group ticketing (additive; Blade unchanged).
 */
class GroupTicketingJsonPresenter
{
    public function __construct(
        protected GroupInventoryCardPresenter $cardPresenter,
        protected GroupBookingRestrictionService $restrictionService,
        protected GroupReservationService $reservationService,
    ) {}

    /**
     * @param  Collection<int, GroupInventory>  $results
     * @return list<array<string, mixed>>
     */
    public function presentResultCards(Collection $results, bool $bookable): array
    {
        return $results
            ->map(function (GroupInventory $inventory) use ($bookable): array {
                $card = $this->cardPresenter->present($inventory, $bookable);

                return $this->normalizeCard($card, $inventory);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    public function presentPackageCard(array $card, GroupInventory $inventory): array
    {
        $normalized = $this->normalizeCard($card, $inventory);
        $normalized['package_notes'] = $inventory->refund_change_notes;
        $normalized['booking_conditions'] = [
            'hold_minutes' => $this->reservationService->holdMinutes(),
            'manual_payment_only' => true,
            'hold_starts_at_review_confirm' => true,
        ];
        $normalized['seat_selection'] = [
            'available' => false,
            'message' => 'Seat selection is not currently available for this booking. Seat assignment is subject to airline or ticketing confirmation.',
        ];

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public function presentPassengersContext(GroupInventory $inventory, array $card, int $seatCount): array
    {
        $maxSeats = $inventory->availableSeats();
        $initialSeats = max(1, min($maxSeats, $seatCount));

        return [
            'inventory' => $this->presentPackageCard($card, $inventory),
            'seat_count' => $initialSeats,
            'max_seats' => $maxSeats,
            'countries' => CountryList::forSelect(),
            'checkout_summary' => $this->cardPresenter->buildCheckoutSummary($card, $initialSeats),
            'passenger_fields' => $this->passengerFieldContract(),
            'contact_fields' => $this->contactFieldContract(),
            'progress' => $this->progressState('passengers'),
            'auth_required' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentBooking(GroupBooking $booking, array $card): array
    {
        $booking->loadMissing(['passengers', 'inventory']);

        return [
            'id' => $booking->id,
            'reference' => $booking->reference,
            'status' => $booking->status->value,
            'status_label' => $booking->status->label(),
            'payment_status' => $this->paymentStatus($booking),
            'payment_status_label' => $this->paymentStatusLabel($booking),
            'seat_count' => (int) $booking->seat_count,
            'total_amount' => (float) $booking->total_amount,
            'total_formatted' => number_format((float) $booking->total_amount, 0),
            'currency' => (string) $booking->currency,
            'expires_at' => $booking->expires_at?->toIso8601String(),
            'server_time' => now()->toIso8601String(),
            'hold_minutes' => $this->reservationService->holdMinutes(),
            'is_expired' => $booking->isExpired(),
            'is_releasable' => $booking->isReleasable(),
            'is_payment_window_open' => $booking->isPaymentWindowOpen(),
            'contact' => [
                'name' => $booking->contact_name,
                'email' => $booking->contact_email,
                'phone' => $booking->contact_phone,
            ],
            'passengers' => $booking->passengers->map(fn ($passenger): array => [
                'title' => $passenger->title,
                'first_name' => $passenger->first_name,
                'last_name' => $passenger->last_name,
                'full_name' => $passenger->fullName(),
                'gender' => $passenger->gender,
                'date_of_birth' => $passenger->date_of_birth?->format('Y-m-d'),
                'nationality' => $passenger->nationality,
                'document_type' => $passenger->document_type,
                'passport_number' => $passenger->passport_number,
                'passport_issue_date' => $passenger->passport_issue_date?->format('Y-m-d'),
                'passport_expiry' => $passenger->passport_expiry?->format('Y-m-d'),
                'passenger_type' => $passenger->passenger_type ?? 'adult',
            ])->values()->all(),
            'inventory' => $this->presentPackageCard($card, $booking->inventory),
            'checkout_summary' => $this->cardPresenter->buildCheckoutSummary(
                $card,
                (int) $booking->seat_count,
                (float) $booking->total_amount,
            ),
            'progress' => $this->progressStateFromBooking($booking),
            'seat_selection' => [
                'available' => false,
                'message' => 'Seat selection is not currently available for this booking. Seat assignment is subject to airline or ticketing confirmation.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentReview(GroupBooking $booking, array $card): array
    {
        return array_merge($this->presentBooking($booking, $card), [
            'hold_notice' => 'Your seats will be reserved for '.$this->reservationService->holdMinutes().' minutes after confirmation.',
            'manual_payment_notice' => 'No payment is taken on this step.',
            'progress' => $this->progressState('review', $booking),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function presentPayment(GroupBooking $booking, array $card): array
    {
        return array_merge($this->presentBooking($booking, $card), [
            'payment_methods' => $this->paymentMethods(),
            'payment_proof_supported' => true,
            'payment_reference_required' => true,
            'status_message' => 'Awaiting manual payment submission',
            'instructions' => [
                'Include your booking reference in the payment note.',
                'Submit your payment reference and optional proof below.',
            ],
            'support' => $this->supportContacts(),
            'progress' => $this->progressState('payment', $booking),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function presentConfirmation(GroupBooking $booking, array $card): array
    {
        $payload = $this->presentBooking($booking, $card);
        $payload['hero'] = $this->confirmationHero($booking);
        $payload['support'] = $this->supportContacts();
        $payload['progress'] = $this->progressState('confirmation', $booking);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function presentLockState(?\App\Models\User $user): array
    {
        if ($user === null) {
            return [
                'locked' => false,
                'unpaid_release_count' => 0,
                'block_threshold' => GroupBookingRestrictionService::BLOCK_THRESHOLD,
            ];
        }

        $locked = $this->restrictionService->isBlocked($user);
        $count = $this->restrictionService->unpaidReleaseCount($user);

        return [
            'locked' => $locked,
            'unpaid_release_count' => $count,
            'block_threshold' => GroupBookingRestrictionService::BLOCK_THRESHOLD,
            'message' => $locked
                ? GroupBookingRestrictionService::BLOCK_THRESHOLD.' unpaid group reservations expired without payment. Your group booking access is temporarily restricted. Please contact support.'
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private function normalizeCard(array $card, ?GroupInventory $inventory = null): array
    {
        $publicId = $inventory?->public_id ?: (isset($card['id']) ? (string) $card['id'] : null);

        return [
            'id' => $card['id'] ?? null,
            'public_id' => $publicId,
            'title' => $card['title'] ?? '',
            'sector_code' => $card['sector_code'] ?? '',
            'route_line' => $card['route_line'] ?? '',
            'departure_date' => $card['departure_date'] ?? null,
            'departure_date_short' => $card['departure_date_short'] ?? null,
            'departure_datetime_display' => $card['departure_datetime_display'] ?? null,
            'arrival_time_display' => $card['arrival_time_display'] ?? null,
            'airline_name' => $card['airline_name'] ?? '',
            'airline_code' => $card['airline_code'] ?? null,
            'airline_logo_url' => $card['airline_logo_url'] ?? null,
            'baggage' => $card['baggage'] ?? [],
            'baggage_line' => $card['baggage_line'] ?? '',
            'meal_label' => $card['meal_label'] ?? null,
            'price_formatted' => $card['price_formatted'] ?? '',
            'currency' => $card['currency'] ?? 'PKR',
            'available_seats' => (int) ($card['available_seats'] ?? 0),
            'seat_label' => $card['seat_label'] ?? '',
            'seats_badge_variant' => $card['seats_badge_variant'] ?? 'ok',
            'cta_label' => $card['cta_label'] ?? '',
            'cta_disabled' => (bool) ($card['cta_disabled'] ?? true),
            'cta_message' => $card['cta_message'] ?? null,
            'bookable' => ! ($card['cta_disabled'] ?? true),
            'show_path' => $card['show_url'] ?? null,
            'passengers_path' => $card['cta_url'] ?? null,
        ];
    }

    /**
     * @return list<array{key: string, label: string, state: string, href: ?string}>
     */
    public function progressState(string $activeStep, ?GroupBooking $booking = null): array
    {
        $steps = [
            ['key' => 'package', 'label' => 'Package Selected'],
            ['key' => 'passengers', 'label' => 'Passenger Details'],
            ['key' => 'review', 'label' => 'Review'],
            ['key' => 'payment', 'label' => 'Manual Payment'],
            ['key' => 'confirmation', 'label' => 'Confirmation'],
        ];

        $completedThrough = match (true) {
            $booking === null && in_array($activeStep, ['review', 'payment', 'confirmation'], true) => 'package',
            $booking === null => $activeStep === 'package' ? null : 'package',
            in_array($booking->status, [GroupBookingStatus::Confirmed], true) => 'confirmation',
            in_array($booking->status, [GroupBookingStatus::ManualPaymentPendingReview, GroupBookingStatus::ManualPaymentSubmitted], true) => 'payment',
            in_array($booking->status, [GroupBookingStatus::ReservedAwaitingPayment, GroupBookingStatus::PaymentPending], true) => 'review',
            $booking->status === GroupBookingStatus::PendingPassengerDetails => 'passengers',
            default => null,
        };

        $order = array_column($steps, 'key');
        $activeIndex = array_search($activeStep, $order, true);
        $completedIndex = $completedThrough !== null ? array_search($completedThrough, $order, true) : -1;

        return array_map(function (array $step) use ($activeStep, $activeIndex, $completedIndex, $booking): array {
            $index = array_search($step['key'], ['package', 'passengers', 'review', 'payment', 'confirmation'], true);
            $state = 'upcoming';
            if ($step['key'] === $activeStep) {
                $state = 'current';
            } elseif ($completedIndex !== false && $index !== false && $index <= $completedIndex) {
                $state = 'completed';
            }

            return [
                'key' => $step['key'],
                'label' => $step['label'],
                'state' => $state,
                'href' => $this->progressHref($step['key'], $booking),
            ];
        }, $steps);
    }

    /**
     * @return list<array{key: string, label: string, state: string, href: ?string}>
     */
    private function progressStateFromBooking(GroupBooking $booking): array
    {
        $active = match ($booking->status) {
            GroupBookingStatus::PendingPassengerDetails => 'review',
            GroupBookingStatus::ReservedAwaitingPayment, GroupBookingStatus::PaymentPending => 'payment',
            GroupBookingStatus::ManualPaymentPendingReview, GroupBookingStatus::ManualPaymentSubmitted => 'confirmation',
            GroupBookingStatus::Confirmed => 'confirmation',
            default => 'review',
        };

        return $this->progressState($active, $booking);
    }

    private function progressHref(string $stepKey, ?GroupBooking $booking): ?string
    {
        if ($booking === null) {
            return null;
        }

        $inventory = $booking->inventory;
        $publicId = $inventory?->public_id ?: (string) ($inventory?->id ?? '');

        return match ($stepKey) {
            'package' => $publicId !== '' ? '/groups/'.$publicId : null,
            'passengers' => $booking->status === GroupBookingStatus::PendingPassengerDetails && $publicId !== ''
                ? '/groups/'.$publicId.'/passengers'
                : null,
            'review' => '/groups/booking/'.$booking->reference.'/review',
            'payment' => in_array($booking->status, [
                GroupBookingStatus::ReservedAwaitingPayment,
                GroupBookingStatus::PaymentPending,
            ], true) ? '/groups/booking/'.$booking->reference.'/payment' : null,
            'confirmation' => '/groups/booking/'.$booking->reference.'/confirmation',
            default => null,
        };
    }

    private function paymentStatus(GroupBooking $booking): string
    {
        return match ($booking->status) {
            GroupBookingStatus::Confirmed => 'verified',
            GroupBookingStatus::ManualPaymentPendingReview, GroupBookingStatus::ManualPaymentSubmitted => 'pending_review',
            GroupBookingStatus::ReservedAwaitingPayment, GroupBookingStatus::PaymentPending => 'awaiting_payment',
            GroupBookingStatus::Released, GroupBookingStatus::Expired => 'expired',
            default => 'unknown',
        };
    }

    private function paymentStatusLabel(GroupBooking $booking): string
    {
        return match ($booking->status) {
            GroupBookingStatus::Confirmed => 'Payment verified',
            GroupBookingStatus::ManualPaymentPendingReview, GroupBookingStatus::ManualPaymentSubmitted => 'Manual payment pending admin review',
            GroupBookingStatus::ReservedAwaitingPayment, GroupBookingStatus::PaymentPending => 'Awaiting payment',
            default => $booking->status->label(),
        };
    }

    /**
     * @return list<array{value: string, title: string, hint: string}>
     */
    private function paymentMethods(): array
    {
        return [
            [
                'value' => 'bank_transfer',
                'title' => 'Bank transfer',
                'hint' => 'Transfer the total amount and include your booking reference in the payment note.',
            ],
            [
                'value' => 'office',
                'title' => 'Pay at office / consultant',
                'hint' => 'Visit our office or speak with your travel consultant to complete payment.',
            ],
            [
                'value' => 'cash',
                'title' => 'Cash deposit',
                'hint' => 'Deposit cash at our office and submit your receipt reference below.',
            ],
        ];
    }

    /**
     * @return array{phone: ?string, whatsapp: ?string, email: ?string, support_path: string}
     */
    private function supportContacts(): array
    {
        return [
            'phone' => config('ota-client.support_phone'),
            'whatsapp' => config('ota-client.support_whatsapp'),
            'email' => config('ota-client.support_email'),
            'support_path' => '/support',
        ];
    }

    /**
     * @return array{title: string, subtitle: string, status_label: string}
     */
    private function confirmationHero(GroupBooking $booking): array
    {
        return match ($booking->status) {
            GroupBookingStatus::Confirmed => [
                'title' => 'Booking confirmed',
                'subtitle' => 'Your group booking is confirmed. Thank you for your payment.',
                'status_label' => 'Confirmed',
            ],
            GroupBookingStatus::ManualPaymentPendingReview, GroupBookingStatus::ManualPaymentSubmitted => [
                'title' => 'Payment submitted',
                'subtitle' => 'Your payment has been submitted and is pending admin verification. We will notify you once confirmed.',
                'status_label' => 'Payment under review',
            ],
            GroupBookingStatus::ReservedAwaitingPayment, GroupBookingStatus::PaymentPending => [
                'title' => 'Booking update',
                'subtitle' => 'Your reservation is held. Please complete payment before it expires.',
                'status_label' => 'Awaiting payment',
            ],
            default => [
                'title' => 'Booking update',
                'subtitle' => 'Status: '.$booking->status->label(),
                'status_label' => $booking->status->label(),
            ],
        };
    }

    /**
     * @return list<array{name: string, required: bool}>
     */
    private function passengerFieldContract(): array
    {
        return [
            ['name' => 'title', 'required' => true],
            ['name' => 'first_name', 'required' => true],
            ['name' => 'last_name', 'required' => true],
            ['name' => 'gender', 'required' => true],
            ['name' => 'date_of_birth', 'required' => true],
            ['name' => 'nationality', 'required' => true],
            ['name' => 'document_type', 'required' => true],
            ['name' => 'passport_number', 'required' => true],
            ['name' => 'passport_issue_date', 'required' => false],
            ['name' => 'passport_expiry', 'required' => true],
            ['name' => 'passenger_type', 'required' => false],
        ];
    }

    /**
     * @return list<array{name: string, required: bool}>
     */
    private function contactFieldContract(): array
    {
        return [
            ['name' => 'contact_name', 'required' => true],
            ['name' => 'contact_email', 'required' => true],
            ['name' => 'contact_phone', 'required' => true],
        ];
    }
}
