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

class SabrePublicConfirmationOutcomePhase17CTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_view_distinguishes_pnr_from_pending_request(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        $withPnr = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'pnr' => 'ABCDE',
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => ['supplier_provider' => SupplierProvider::Sabre->value],
        ]);

        $withoutPnr = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'pnr' => null,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => ['supplier_provider' => SupplierProvider::Sabre->value],
        ]);

        $pnrHtml = view('themes.frontend.jetpakistan.frontend.booking.partials.confirmation-body', [
            'booking' => $withPnr,
            'draft' => [],
            'offer' => null,
            'criteria' => [],
            'errors' => new ViewErrorBag,
            'supplierProvider' => SupplierProvider::Sabre->value,
            'supplierConfirmationNotice' => null,
        ])->render();

        $pendingHtml = view('themes.frontend.jetpakistan.frontend.booking.partials.confirmation-body', [
            'booking' => $withoutPnr,
            'draft' => [],
            'offer' => null,
            'criteria' => [],
            'errors' => new ViewErrorBag,
            'supplierProvider' => SupplierProvider::Sabre->value,
            'supplierConfirmationNotice' => [
                'notice' => 'Booking request saved. Sabre returned a response requiring staff review. No ticket has been issued.',
            ],
        ])->render();

        $this->assertStringContainsString('Sabre PNR', $pnrHtml);
        $this->assertStringContainsString('ABCDE', $pnrHtml);
        $this->assertStringContainsString('staff review', strtolower($pendingHtml));
        $this->assertStringNotContainsString('Booking confirmed', $pendingHtml);
    }
}
