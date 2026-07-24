<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class SabreConfirmationOutcomePhase17DTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_success_shows_pnr_and_not_ticketed_as_confirmed(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'pnr' => 'ABCDE',
            'ticketing_status' => 'pending',
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => ['supplier_provider' => SupplierProvider::Sabre->value],
        ]);

        $html = view('themes.frontend.jetpakistan.frontend.booking.partials.confirmation-body', [
            'booking' => $booking,
            'draft' => [],
            'offer' => null,
            'criteria' => [],
            'errors' => new ViewErrorBag,
            'supplierProvider' => SupplierProvider::Sabre->value,
            'supplierConfirmationNotice' => null,
        ])->render();

        $this->assertStringContainsString('ABCDE', $html);
        $this->assertStringContainsString('Ticketing is still pending', $html);
        $this->assertStringNotContainsString('Booking confirmed', $html);
    }

    public function test_needs_review_notice_does_not_claim_supplier_confirmed(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'pnr' => null,
            'supplier' => SupplierProvider::Sabre->value,
        ]);

        $html = view('themes.frontend.jetpakistan.frontend.booking.partials.confirmation-body', [
            'booking' => $booking,
            'draft' => [],
            'offer' => null,
            'criteria' => [],
            'errors' => new ViewErrorBag,
            'supplierProvider' => SupplierProvider::Sabre->value,
            'supplierConfirmationNotice' => [
                'notice' => 'Booking request saved. Sabre returned a response requiring staff review. No ticket has been issued.',
            ],
        ])->render();

        $this->assertStringContainsString('staff review', strtolower($html));
        $this->assertStringNotContainsString('Your booking is confirmed', $html);
    }
}
