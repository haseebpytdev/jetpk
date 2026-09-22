<?php

namespace Tests\Unit\Ai;

use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Support\Ai\AiAssistantBookingChatPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAssistantBookingChatPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_presenter_exposes_minimum_customer_summary_only(): void
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'booking_reference' => 'SAFE123',
            'status' => BookingStatus::PaymentPending,
            'meta' => [
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => '2026-12-22',
                    'trip_type' => 'one_way',
                ],
            ],
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.com',
            'phone' => '+923001112233',
        ]);
        BookingPassenger::query()->create([
            'booking_id' => $booking->id,
            'first_name' => 'Hidden',
            'last_name' => 'Passenger',
            'passport_number' => 'AB1234567',
        ]);

        $presenter = app(AiAssistantBookingChatPresenter::class);
        $payload = $presenter->present($booking->fresh());

        $this->assertSame('SAFE123', $payload['booking_reference']);
        $this->assertArrayNotHasKey('passport_number', $payload);
        $this->assertArrayNotHasKey('email', $payload);
        $this->assertArrayNotHasKey('phone', $payload);
        $this->assertArrayNotHasKey('internal_notes', $payload);

        $summary = $presenter->summarizeForChat($payload);
        $this->assertStringNotContainsString('AB1234567', $summary);
        $this->assertStringNotContainsString('guest@example.com', $summary);
    }
}
