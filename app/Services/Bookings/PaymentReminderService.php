<?php

namespace App\Services\Bookings;

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Communication\BookingCommunicationService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Scheduled payment reminders for unpaid bookings. Deduped per reminder stage.
 * Suppressed for paid / ticketed / cancelled / expired bookings.
 */
class PaymentReminderService
{
    public const STAGE_FIRST = 'first';

    public const STAGE_FINAL = 'final';

    public function __construct(
        protected BookingCommunicationService $communicationService,
    ) {}

    /**
     * @return list<BookingStatus>
     */
    public function eligibleStatuses(): array
    {
        return [
            BookingStatus::Pending,
            BookingStatus::Confirmed,
            BookingStatus::PaymentPending,
            BookingStatus::FareReview,
        ];
    }

    public function isSuppressed(Booking $booking): bool
    {
        if (in_array((string) ($booking->payment_status ?? ''), ['paid'], true)) {
            return true;
        }

        if (in_array($booking->status, [
            BookingStatus::Paid,
            BookingStatus::TicketingPending,
            BookingStatus::Ticketed,
            BookingStatus::Cancelled,
            BookingStatus::Expired,
            BookingStatus::Refunded,
            BookingStatus::Failed,
        ], true)) {
            return true;
        }

        return $booking->payments()
            ->where('status', BookingPaymentStatus::Verified)
            ->exists();
    }

    /**
     * Resolve which reminder stage is due now (if any).
     */
    public function dueStage(Booking $booking): ?string
    {
        if ($booking->payment_due_at === null || $this->isSuppressed($booking)) {
            return null;
        }

        if (! in_array($booking->status, $this->eligibleStatuses(), true)) {
            return null;
        }

        $dueAt = $booking->payment_due_at;
        if ($dueAt->isPast()) {
            return null;
        }

        $anchor = $booking->submitted_at ?? $booking->created_at ?? now();
        $windowSeconds = max(1, $anchor->diffInSeconds($dueAt, false));
        if ($windowSeconds <= 0) {
            return null;
        }

        $remainingSeconds = max(0, now()->diffInSeconds($dueAt, false));
        $finalMinutes = max(1, (int) config('ota.payment_reminders.final_minutes_before', 30));
        $firstFraction = (float) config('ota.payment_reminders.first_remaining_fraction', 0.5);

        if ($remainingSeconds <= ($finalMinutes * 60)) {
            return self::STAGE_FINAL;
        }

        $remainingFraction = $remainingSeconds / $windowSeconds;
        if ($remainingFraction <= $firstFraction) {
            return self::STAGE_FIRST;
        }

        return null;
    }

    /**
     * @return array{sent: bool, stage: ?string, reason: string}
     */
    public function sendIfDue(Booking $booking): array
    {
        $stage = $this->dueStage($booking);
        if ($stage === null) {
            return ['sent' => false, 'stage' => null, 'reason' => 'not_due'];
        }

        try {
            $sent = $this->communicationService->sendPaymentReminder($booking, $stage);
        } catch (Throwable $e) {
            Log::warning('booking.payment_reminder_failed', [
                'booking_id' => $booking->id,
                'stage' => $stage,
                'message' => $e->getMessage(),
            ]);

            return ['sent' => false, 'stage' => $stage, 'reason' => 'error'];
        }

        return [
            'sent' => $sent,
            'stage' => $stage,
            'reason' => $sent ? 'sent' : 'deduped_or_skipped',
        ];
    }

    /**
     * @return array{scanned: int, sent: int, skipped: int}
     */
    public function processDueBatch(?int $limit = null): array
    {
        if (! (bool) config('ota.payment_reminders.enabled', true)) {
            return ['scanned' => 0, 'sent' => 0, 'skipped' => 0];
        }

        $limit = $limit ?? max(1, (int) config('ota.payment_reminders.batch_size', 50));
        $scanned = 0;
        $sent = 0;
        $skipped = 0;

        Booking::query()
            ->whereIn('status', array_map(static fn (BookingStatus $s): string => $s->value, $this->eligibleStatuses()))
            ->where(function ($q): void {
                $q->whereNull('payment_status')
                    ->orWhereNotIn('payment_status', ['paid']);
            })
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '>', now())
            ->orderBy('payment_due_at')
            ->limit($limit)
            ->get()
            ->each(function (Booking $booking) use (&$scanned, &$sent, &$skipped): void {
                $scanned++;
                $result = $this->sendIfDue($booking);
                if ($result['sent']) {
                    $sent++;
                } else {
                    $skipped++;
                }
            });

        return compact('scanned', 'sent', 'skipped');
    }
}
