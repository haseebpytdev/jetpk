<?php

namespace Tests\Unit\Services\Suppliers\OneApi;

use App\Enums\BookingCommunicationEvent;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\OneApi\Booking\OneApiBookingService;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContextStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OneApiBookingAmbiguousTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function ambiguous_book_is_not_retried(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::OneApi,
            'credentials' => [
                'username' => 'u',
                'password' => 'p',
                'agent_code' => 'A',
                'agent_preferred_currency' => 'AED',
                'pos_country' => 'AE',
                'pos_station' => 'DXB',
                'rest_auth_url' => 'https://example.test/auth',
                'rest_search_url' => 'https://example.test/search',
                'soap_url' => 'https://example.test/soap',
            ],
        ]);

        $context = app(OneApiWorkflowContextStore::class)->create($connection->id, 'corr', []);
        $context->moneySnapshot = ['final_price_confirmed' => true];
        app(OneApiWorkflowContextStore::class)->put($context);
        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::OneApi->value,
            'meta' => [
                'one_api_context' => ['workflow_context_id' => $context->contextId],
                'supplier_connection_id' => $connection->id,
            ],
        ]);

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::OneApi->value,
            'action' => 'create_pnr',
            'status' => 'ambiguous',
            'request_payload' => [],
            'response_payload' => ['reconciliation_required' => true],
            'attempted_by' => $user->id,
            'attempted_at' => now(),
        ]);

        $service = app(OneApiBookingService::class);
        $result = $service->createSupplierBooking($booking, $connection, $user, [
            'fixture_path' => base_path('tests/Fixtures/Suppliers/OneApi/book_paid.xml'),
        ]);

        $this->assertFalse($result->success);
        $this->assertSame('ambiguous', $result->status);
        $this->assertSame(1, $booking->supplierBookingAttempts()->count());
        $this->assertSame(
            0,
            CommunicationLog::query()
                ->where('booking_id', $booking->id)
                ->where('event', BookingCommunicationEvent::SupplierBookingCreated->value)
                ->count()
        );
    }
}
