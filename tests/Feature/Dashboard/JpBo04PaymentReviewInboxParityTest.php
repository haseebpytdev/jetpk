<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\User;
use App\Services\Dashboard\AgencyDashboardService;
use App\Services\Dashboard\Authority\OperationalInboxAuthority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JpBo04PaymentReviewInboxParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_badge_count_matches_bookings_awaiting_payment_queue_not_payments_ledger(): void
    {
        $agency = Agency::factory()->create();
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => $agency->id,
        ]);

        Booking::factory()->count(6)->create([
            'agency_id' => $agency->id,
            'payment_status' => 'unpaid',
        ]);
        Booking::factory()->create([
            'agency_id' => $agency->id,
            'payment_status' => 'paid',
        ]);

        // Payment ledger empty — must not drive the bookings-awaiting-payment badge.
        $this->assertSame(0, BookingPayment::query()->count());

        $service = app(AgencyDashboardService::class);
        $dashboard = $service->build($admin);
        $summary = $dashboard['commandSummary'];

        $this->assertSame(6, (int) $summary['payment_review']);
        $this->assertSame(6, (int) $summary['bookings_awaiting_payment']);
        $this->assertSame(0, (int) $summary['payment_proof_review']);

        $inbox = collect($summary['operational_inbox'] ?? []);
        $awaiting = $inbox->firstWhere('key', OperationalInboxAuthority::KEY_BOOKINGS_AWAITING_PAYMENT);
        $this->assertNotNull($awaiting);
        $this->assertSame('Bookings awaiting payment', $awaiting['label']);
        $this->assertSame('/bookings?queue=payment_review', $awaiting['href']);
        $this->assertSame(6, (int) $awaiting['count']);

        $this->actingAs($admin);
        $response = $this->getJson('/api/dashboard/bookings?queue=payment_review&pageSize=50');
        $response->assertOk();
        $bookings = $response->json('data.bookings') ?? [];
        $this->assertCount(6, $bookings);

        $payments = $this->getJson('/api/dashboard/payments?pageSize=50');
        $payments->assertOk();
        $transactions = $payments->json('data.transactions')
            ?? $payments->json('data.payments')
            ?? [];
        $this->assertCount(0, $transactions);
    }

    public function test_payment_proof_review_count_uses_booking_payment_pending_submitted(): void
    {
        $agency = Agency::factory()->create();
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => $agency->id,
        ]);
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'payment_status' => 'partial',
        ]);

        foreach (range(1, 3) as $i) {
            BookingPayment::query()->create([
                'agency_id' => $agency->id,
                'booking_id' => $booking->id,
                'method' => 'bank_transfer',
                'status' => 'submitted',
                'amount' => 1000 + $i,
                'currency' => 'PKR',
                'payment_reference' => 'JPQA-BO04-PROOF-'.$i,
                'submitted_at' => now(),
            ]);
        }
        BookingPayment::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'method' => 'bank_transfer',
            'status' => 'verified',
            'amount' => 500,
            'currency' => 'PKR',
            'payment_reference' => 'JPQA-BO04-VERIFIED',
            'verified_at' => now(),
        ]);

        $authority = app(OperationalInboxAuthority::class);
        $count = $authority->countPaymentProofReview($authority->paymentProofBaseQuery($admin));
        $this->assertSame(3, $count);

        $this->actingAs($admin);
        $response = $this->getJson('/api/dashboard/payments?reconciliation=pending_review&pageSize=50');
        $response->assertOk();
        $rows = $response->json('data.transactions')
            ?? $response->json('data.payments')
            ?? [];
        $this->assertCount(3, $rows);
    }
}
