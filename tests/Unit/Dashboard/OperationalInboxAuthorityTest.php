<?php

namespace Tests\Unit\Dashboard;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\User;
use App\Services\Dashboard\Authority\OperationalInboxAuthority;
use Illuminate\Database\Eloquent\Builder;
use Mockery;
use Tests\TestCase;

class OperationalInboxAuthorityTest extends TestCase
{
    public function test_bookings_awaiting_payment_filter_matches_unpaid_partial(): void
    {
        $authority = new OperationalInboxAuthority;
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('whereIn')
            ->once()
            ->with('payment_status', ['unpaid', 'partial'])
            ->andReturnSelf();

        $result = $authority->applyBookingsAwaitingPaymentFilter($query);
        $this->assertSame($query, $result);
    }

    public function test_payment_proof_review_filter_matches_pending_submitted(): void
    {
        $authority = new OperationalInboxAuthority;
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('whereIn')
            ->once()
            ->with('status', ['pending', 'submitted'])
            ->andReturnSelf();

        $result = $authority->applyPaymentProofReviewFilter($query);
        $this->assertSame($query, $result);
    }

    public function test_inbox_items_deep_link_bookings_awaiting_payment_not_payments(): void
    {
        $authority = new OperationalInboxAuthority;
        $items = $authority->inboxItems([
            'bookings_awaiting_payment' => 6,
            'payment_review' => 6,
            'payment_proof_review' => 0,
            'agency_applications_pending' => 0,
            'pending_deposits' => 0,
            'commissions_requiring_review' => 0,
        ]);

        $awaiting = collect($items)->firstWhere('key', OperationalInboxAuthority::KEY_BOOKINGS_AWAITING_PAYMENT);
        $this->assertNotNull($awaiting);
        $this->assertSame('Bookings awaiting payment', $awaiting['label']);
        $this->assertSame('/bookings?queue=payment_review', $awaiting['href']);
        $this->assertSame(6, $awaiting['count']);

        $proof = collect($items)->firstWhere('key', OperationalInboxAuthority::KEY_PAYMENT_PROOF_REVIEW);
        $this->assertNotNull($proof);
        $this->assertSame('/payments?reconciliation=pending_review', $proof['href']);
        $this->assertSame('Payment proof review', $proof['label']);
    }

    public function test_inbox_items_with_counts_omits_zero(): void
    {
        $authority = new OperationalInboxAuthority;
        $items = $authority->inboxItemsWithCounts([
            'bookings_awaiting_payment' => 6,
            'payment_proof_review' => 0,
            'agency_applications_pending' => 2,
            'pending_deposits' => 0,
            'commissions_requiring_review' => 0,
        ]);

        $keys = array_column($items, 'key');
        $this->assertContains(OperationalInboxAuthority::KEY_BOOKINGS_AWAITING_PAYMENT, $keys);
        $this->assertContains(OperationalInboxAuthority::KEY_AGENCY_APPLICATIONS, $keys);
        $this->assertNotContains(OperationalInboxAuthority::KEY_PAYMENT_PROOF_REVIEW, $keys);
    }
}
