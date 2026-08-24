<?php

namespace App\Services\Dashboard\Authority;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single authority for operational inbox badge counts, labels, and deep links.
 *
 * Booking unpaid/partial is NOT "payment review" — that label is reserved for
 * BookingPayment proof rows awaiting verify/reject.
 */
final class OperationalInboxAuthority
{
    public const KEY_BOOKINGS_AWAITING_PAYMENT = 'bookings_awaiting_payment';

    /** @deprecated Semantic alias kept for booking queue/API compatibility. */
    public const KEY_PAYMENT_REVIEW_LEGACY = 'payment_review';

    public const KEY_PAYMENT_PROOF_REVIEW = 'payment_proof_review';

    public const KEY_AGENCY_APPLICATIONS = 'agency_applications_pending';

    public const KEY_PENDING_DEPOSITS = 'pending_deposits';

    public const KEY_COMMISSIONS = 'commissions_requiring_review';

    /**
     * Bookings with unpaid or partial payment_status (master OTA payment_review queue).
     *
     * @param  Builder<Booking>  $baseQuery
     */
    public function applyBookingsAwaitingPaymentFilter(Builder $baseQuery): Builder
    {
        return $baseQuery->whereIn('payment_status', ['unpaid', 'partial']);
    }

    /**
     * @param  Builder<Booking>  $baseQuery
     */
    public function countBookingsAwaitingPayment(Builder $baseQuery): int
    {
        return (int) $this->applyBookingsAwaitingPaymentFilter(clone $baseQuery)->count();
    }

    /**
     * BookingPayment rows awaiting operator verify/reject.
     *
     * @param  Builder<BookingPayment>  $baseQuery
     */
    public function applyPaymentProofReviewFilter(Builder $baseQuery): Builder
    {
        return $baseQuery->whereIn('status', ['pending', 'submitted']);
    }

    /**
     * @param  Builder<BookingPayment>  $baseQuery
     */
    public function countPaymentProofReview(Builder $baseQuery): int
    {
        return (int) $this->applyPaymentProofReviewFilter(clone $baseQuery)->count();
    }

    /**
     * Scoped BookingPayment query for the actor.
     *
     * @return Builder<BookingPayment>
     */
    public function paymentProofBaseQuery(User $user): Builder
    {
        $query = BookingPayment::query();
        if (! $user->isPlatformAdmin()) {
            $agencyId = $user->current_agency_id;
            if ($agencyId === null) {
                return $query->whereRaw('1 = 0');
            }
            $query->where('agency_id', $agencyId);
        }

        return $query;
    }

    /**
     * Inbox / KPI card definitions with Next-dashboard relative hrefs.
     *
     * @param  array<string, int>  $counts
     * @return list<array{key: string, label: string, count: int, href: string, helper: string}>
     */
    public function inboxItems(array $counts): array
    {
        return [
            [
                'key' => self::KEY_AGENCY_APPLICATIONS,
                'label' => 'Agency applications pending',
                'count' => (int) ($counts[self::KEY_AGENCY_APPLICATIONS] ?? 0),
                'href' => '/agents/applications',
                'helper' => 'Agency applications awaiting decision.',
            ],
            [
                'key' => self::KEY_PENDING_DEPOSITS,
                'label' => 'Pending deposits',
                'count' => (int) ($counts[self::KEY_PENDING_DEPOSITS] ?? 0),
                'href' => '/deposits',
                'helper' => 'Agency fund-load requests awaiting approval.',
            ],
            [
                'key' => self::KEY_BOOKINGS_AWAITING_PAYMENT,
                'label' => 'Bookings awaiting payment',
                'count' => (int) ($counts[self::KEY_BOOKINGS_AWAITING_PAYMENT]
                    ?? $counts[self::KEY_PAYMENT_REVIEW_LEGACY]
                    ?? 0),
                'href' => '/bookings?queue=payment_review',
                'helper' => 'Bookings with unpaid or partial payment status.',
            ],
            [
                'key' => self::KEY_PAYMENT_PROOF_REVIEW,
                'label' => 'Payment proof review',
                'count' => (int) ($counts[self::KEY_PAYMENT_PROOF_REVIEW] ?? 0),
                'href' => '/payments?reconciliation=pending_review',
                'helper' => 'Submitted or pending payment proofs awaiting verify/reject.',
            ],
            [
                'key' => self::KEY_COMMISSIONS,
                'label' => 'Commissions requiring review',
                'count' => (int) ($counts[self::KEY_COMMISSIONS] ?? 0),
                'href' => '/commissions',
                'helper' => 'Commission entries awaiting operator review.',
            ],
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{key: string, label: string, count: int, href: string, helper: string}>
     */
    public function inboxItemsWithCounts(array $counts): array
    {
        return array_values(array_filter(
            $this->inboxItems($counts),
            static fn (array $item): bool => (int) ($item['count'] ?? 0) > 0
        ));
    }
}
