<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingReferenceRouteBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_booking_note_route_accepts_public_reference(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $agency = Agency::factory()->create();
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => $agency->id,
        ]);
        $booking = Booking::factory()->for($agency)->create([
            'booking_reference' => 'JPQA-BIND-REF-001',
            'status' => BookingStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->post('/admin/bookings/JPQA-BIND-REF-001/notes?format=json', [
                'note' => 'Reference-bound internal note',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('booking_notes', [
            'booking_id' => $booking->id,
            'note' => 'Reference-bound internal note',
        ]);
    }
}
