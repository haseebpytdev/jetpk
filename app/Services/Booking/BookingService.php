<?php

namespace App\Services\Booking;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingNote;
use App\Models\BookingPassenger;
use App\Models\BookingStatusLog;
use App\Models\User;
use App\Services\Communication\BookingCommunicationService;
use App\Services\Finance\Ledger\LedgerEventRecorder;
use App\Support\References\CompactReferenceGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class BookingService
{
    public function __construct(
        protected BookingCommunicationService $communicationService,
        protected CompactReferenceGenerator $referenceGenerator,
    ) {}

    public function createDraftBooking(Agency $agency, ?User $customer = null, ?Agent $agent = null): Booking
    {
        if ($agent !== null && $agent->agency_id !== $agency->id) {
            throw new InvalidArgumentException('Agent does not belong to the given agency.');
        }

        return Booking::query()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer?->id,
            'agent_id' => $agent?->id,
            'status' => BookingStatus::Draft,
            'currency' => 'PKR',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function attachPassenger(Booking $booking, array $attributes): BookingPassenger
    {
        return $booking->passengers()->create($attributes);
    }

    /**
     * @param  list<array<string, mixed>>  $passengers
     * @return Collection<int, BookingPassenger>
     */
    public function attachPassengers(Booking $booking, array $passengers): Collection
    {
        $booking->passengers()->delete();

        $created = [];
        foreach ($passengers as $passenger) {
            $created[] = $booking->passengers()->create($passenger);
        }

        return new Collection($created);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function attachContact(Booking $booking, array $attributes): BookingContact
    {
        return $booking->contact()->updateOrCreate(
            ['booking_id' => $booking->id],
            $attributes
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function attachFareBreakdown(Booking $booking, array $attributes): BookingFareBreakdown
    {
        return $booking->fareBreakdown()->updateOrCreate(
            ['booking_id' => $booking->id],
            $attributes
        );
    }

    public function submitBookingRequest(Booking $booking, ?User $actor = null): Booking
    {
        if ($booking->status !== BookingStatus::Draft) {
            throw new InvalidArgumentException('Only draft bookings can be submitted.');
        }

        DB::transaction(function () use ($booking, $actor): void {
            $booking->booking_reference = $this->uniqueBookingReference($booking);
            $booking->submitted_at = now();
            if ($actor !== null && ! $actor->isCustomer()) {
                $booking->meta = $this->mergeCreatorContext($booking, $actor);
            }
            $booking->save();

            $this->changeStatus($booking, BookingStatus::Pending, $actor, 'Booking request submitted');
        });

        $booking = $booking->fresh();

        try {
            $this->communicationService->sendBookingRequestReceived($booking, $actor);
        } catch (Throwable $e) {
            Log::warning('booking.submit.notification_failed', [
                'booking_id' => $booking->id,
                'booking_reference' => (string) ($booking->booking_reference ?? ''),
                'event' => 'booking_request_received',
                'class' => self::class,
                'method' => 'submitBooking',
                'message' => $e->getMessage(),
            ]);
        }

        return $booking;
    }

    /**
     * @return list<BookingStatus>
     */
    public function getAllowedStatusTransitions(Booking $booking, ?User $actor = null): array
    {
        if ($actor?->isPlatformAdmin()) {
            return array_values(array_filter(
                BookingStatus::cases(),
                fn (BookingStatus $s): bool => $s !== $booking->status
            ));
        }

        return match ($booking->status) {
            BookingStatus::Draft => [BookingStatus::Pending],
            BookingStatus::Pending => [BookingStatus::FareReview, BookingStatus::Confirmed, BookingStatus::Cancelled],
            BookingStatus::FareReview => [BookingStatus::Confirmed, BookingStatus::Cancelled],
            BookingStatus::Confirmed => [BookingStatus::PaymentPending, BookingStatus::Cancelled],
            BookingStatus::PaymentPending => [BookingStatus::Paid, BookingStatus::Cancelled],
            BookingStatus::Paid => [BookingStatus::TicketingPending, BookingStatus::Refunded],
            BookingStatus::TicketingPending => [BookingStatus::Ticketed, BookingStatus::Failed],
            BookingStatus::Failed => [BookingStatus::TicketingPending, BookingStatus::Cancelled],
            BookingStatus::Ticketed => [BookingStatus::Refunded],
            BookingStatus::Cancelled, BookingStatus::Expired, BookingStatus::Refunded => [],
        };
    }

    public function changeStatus(
        Booking $booking,
        BookingStatus $to,
        ?User $actor = null,
        ?string $note = null,
        ?array $context = null,
    ): Booking {
        DB::transaction(function () use ($booking, $to, $actor, $note, $context): void {
            $booking->refresh();
            $from = $booking->status;

            if ($from === $to) {
                return;
            }

            $allowed = $this->getAllowedStatusTransitions($booking, $actor);
            if (! $actor?->isPlatformAdmin() && ! in_array($to, $allowed, true)) {
                throw new InvalidArgumentException('Invalid status transition from '.$from->value.' to '.$to->value.'.');
            }

            $oldValues = ['status' => $from->value];

            $booking->status = $to;

            if ($to === BookingStatus::Confirmed) {
                $booking->confirmed_at = now();
            }

            $booking->save();

            $newValues = ['status' => $to->value];

            $this->addStatusLog($booking, $from, $to, $actor, $note, $context);

            $this->writeBookingAudit($booking, $actor, 'booking.status_changed', $oldValues, $newValues);
        });

        $booking = $booking->fresh();
        if ($to === BookingStatus::Confirmed) {
            app(LedgerEventRecorder::class)->recordMarkupRevenueForBooking($booking, $actor);
        }

        $this->communicationService->sendBookingStatusChanged($booking, str_replace('_', ' ', $to->value));
        if ($to === BookingStatus::Confirmed) {
            $this->communicationService->sendBookingConfirmed($booking);
        }

        return $booking;
    }

    public function addInternalNote(Booking $booking, User $user, string $note, bool $customerVisible = false): BookingNote
    {
        return DB::transaction(function () use ($booking, $user, $note, $customerVisible): BookingNote {
            $bookingNote = $booking->bookingNotes()->create([
                'agency_id' => $booking->agency_id,
                'user_id' => $user->id,
                'note_type' => 'internal',
                'note' => $note,
                'is_customer_visible' => $customerVisible,
            ]);

            $this->writeBookingAudit($booking, $user, 'booking.note_added', [], [
                'booking_note_id' => $bookingNote->id,
                'note_excerpt' => Str::limit($note, 200),
                'is_customer_visible' => $customerVisible,
            ]);

            return $bookingNote;
        });
    }

    public function assignStaff(Booking $booking, ?User $assignee, User $actor): Booking
    {
        if ($assignee !== null) {
            $this->assertAssignableStaff($booking, $assignee);
        }

        return DB::transaction(function () use ($booking, $assignee, $actor): Booking {
            $booking->refresh();
            $oldValues = [
                'assigned_staff_id' => $booking->assigned_staff_id,
                'assigned_at' => $booking->assigned_at?->toIso8601String(),
            ];

            $booking->assigned_staff_id = $assignee?->id;
            $booking->assigned_at = $assignee !== null ? now() : null;
            $booking->save();

            $newValues = [
                'assigned_staff_id' => $booking->assigned_staff_id,
                'assigned_at' => $booking->assigned_at?->toIso8601String(),
            ];

            $this->writeBookingAudit($booking, $actor, 'booking.staff_assigned', $oldValues, $newValues);

            $fresh = $booking->fresh();
            $fresh->loadMissing('assignedStaff');
            $this->communicationService->sendStaffAssigned($fresh, $fresh->assignedStaff);

            return $fresh;
        });
    }

    public function addStatusLog(
        Booking $booking,
        ?BookingStatus $from,
        BookingStatus $to,
        ?User $actor = null,
        ?string $note = null,
        ?array $context = null,
    ): BookingStatusLog {
        return $booking->statusLogs()->create([
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'user_id' => $actor?->id,
            'note' => $note,
            'context' => $context,
        ]);
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    protected function writeBookingAudit(Booking $booking, ?User $actor, string $action, array $oldValues, array $newValues): void
    {
        AuditLog::query()->create([
            'agency_id' => $booking->agency_id,
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => Booking::class,
            'auditable_id' => $booking->id,
            'properties' => [
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ],
        ]);
    }

    protected function assertAssignableStaff(Booking $booking, User $assignee): void
    {
        if ($assignee->current_agency_id !== $booking->agency_id) {
            throw new InvalidArgumentException('Staff member is not in this agency.');
        }

        if (! $assignee->isStaff()) {
            throw new InvalidArgumentException('Only staff can be assigned.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function mergeCreatorContext(Booking $booking, User $actor): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        if (is_array($meta['creator_context'] ?? null)) {
            return $meta;
        }

        $role = $actor->account_type instanceof AccountType
            ? $actor->account_type->value
            : (string) $actor->account_type;

        $context = [
            'creator_user_id' => $actor->id,
            'creator_role' => $role,
            'creator_source' => 'authenticated_actor',
        ];

        if ($actor->isAgentStaff()) {
            $context['agent_staff_creator_user_id'] = $actor->id;
        }

        $meta['creator_context'] = $context;

        return $meta;
    }

    protected function uniqueBookingReference(Booking $booking): string
    {
        return $this->referenceGenerator->generateUnique('bookings', 'booking_reference', 8);
    }
}
