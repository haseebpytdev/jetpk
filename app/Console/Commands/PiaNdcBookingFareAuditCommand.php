<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\PiaNdc\PiaNdcXmlBuilder;
use App\Support\Bookings\AdminPiaNdcSelectedFarePresenter;
use App\Support\Phone\SupplierContactFormatter;
use Illuminate\Console\Command;

class PiaNdcBookingFareAuditCommand extends Command
{
    protected $signature = 'pia:ndc-booking-fare-audit {--booking= : Booking ID}';

    protected $description = 'Read-only audit: PIA NDC selected branded fare + supplier contact phone shape for a booking';

    public function handle(
        PiaNdcXmlBuilder $xmlBuilder,
        AdminPiaNdcSelectedFarePresenter $farePresenter,
    ): int {
        $bookingId = (int) $this->option('booking');
        if ($bookingId <= 0) {
            $this->error('Provide --booking={id}');

            return self::FAILURE;
        }

        $booking = Booking::query()->with('contact')->find($bookingId);
        if ($booking === null) {
            $this->error('Booking not found: '.$bookingId);

            return self::FAILURE;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== SupplierProvider::PiaNdc->value) {
            $this->warn('Booking is not PIA NDC (provider='.$provider.').');

            return self::SUCCESS;
        }

        $this->line('booking_id='.$booking->id);

        $selected = is_array($meta['selected_fare_family_option'] ?? null)
            ? $meta['selected_fare_family_option']
            : [];
        $ctx = is_array($selected['provider_context'] ?? null) ? $selected['provider_context'] : [];

        $this->line('selected_brand='.($selected['name'] ?? $selected['brand_name'] ?? '—'));
        $this->line('fare_basis='.($selected['fare_basis'] ?? $ctx['fare_basis'] ?? '—'));
        $this->line('booking_class='.($selected['booking_class'] ?? $ctx['rbd'] ?? '—'));
        $this->line('baggage='.($selected['check_in_summary'] ?? $selected['baggage_summary'] ?? '—'));
        $this->line('offer_ref_id='.(trim((string) ($ctx['offer_ref_id'] ?? '')) !== '' ? 'present' : 'missing'));
        $this->line('offer_item_ref_id='.(trim((string) ($ctx['offer_item_ref_id'] ?? '')) !== '' ? 'present' : 'missing'));
        $this->line('selected_fare_total='.($booking->selected_fare_total ?? '—'));
        $this->line('revalidated_fare_total='.($booking->revalidated_fare_total ?? '—'));

        $contact = $xmlBuilder->buildContactFromBooking($booking);
        $formatted = SupplierContactFormatter::fromBooking($booking);
        $phoneOk = (bool) ($formatted['valid'] ?? false);
        $this->line('supplier_contact_phone_normalized='.($phoneOk ? 'yes' : 'no'));
        $this->line('supplier_phone_country='.($contact['phone_country'] ?? '—'));
        $this->line('supplier_phone_number='.($contact['phone_number'] !== '' ? $contact['phone_number'] : 'missing'));
        $this->line('ctcm_preview='.($formatted['ctcm_text'] ?? '—'));

        $panel = $farePresenter->panel($booking);
        $this->line('admin_display_fields_present='.(($panel['show'] ?? false) ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
