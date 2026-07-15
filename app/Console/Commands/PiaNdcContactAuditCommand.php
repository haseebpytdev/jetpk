<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\PiaNdc\PiaNdcXmlBuilder;
use App\Support\Phone\SupplierContactFormatter;
use Illuminate\Console\Command;

/**
 * Read-only PIA NDC / Hitit supplier contact shape audit (no supplier mutations).
 */
class PiaNdcContactAuditCommand extends Command
{
    protected $signature = 'pia:ndc-contact-audit {--booking= : Booking ID}';

    protected $description = 'Read-only audit: PIA NDC supplier contact phone, CTCM/CTCB, and Contact Person previews';

    public function handle(PiaNdcXmlBuilder $xmlBuilder): int
    {
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
            $this->warn('Booking is not PIA NDC (provider='.$provider.'). Continuing with contact audit anyway.');
        }

        $contact = $booking->contact;
        $contactMeta = is_array($contact?->meta) ? $contact->meta : [];
        $phoneRaw = trim((string) ($contact?->phone ?? $booking->contact_phone ?? ''));
        $countryCode = trim((string) (
            $contactMeta['phone_country_code']
            ?? $meta['phone_country_code']
            ?? ''
        ));
        $areaCode = trim((string) (
            $contactMeta['phone_area_code']
            ?? $contactMeta['area_code']
            ?? $meta['phone_area_code']
            ?? ''
        ));

        $formatted = SupplierContactFormatter::fromBooking($booking);
        $supplierContact = $xmlBuilder->buildContactFromBooking($booking);
        $legacy = SupplierContactFormatter::legacyBadPreviews(
            $phoneRaw,
            $countryCode !== '' ? $countryCode : '92',
            $areaCode !== '' ? $areaCode : '0',
        );
        $audit = $formatted['audit'] ?? [];

        $this->line('booking_id='.$booking->id);
        $this->line('raw_booking_phone='.($phoneRaw !== '' ? $phoneRaw : '—'));
        $this->line('country_code='.($countryCode !== '' ? $countryCode : '—'));
        $this->line('area_code='.($areaCode !== '' ? $areaCode : '—'));
        $this->line('normalized_e164='.($formatted['e164'] ?? '—'));
        $this->line('supplier_country_code='.($formatted['country_code'] ?? '—'));
        $this->line('supplier_national_number='.($formatted['national_number'] ?? '—'));
        $this->line('xml_phone_country='.($supplierContact['phone_country'] ?? '—'));
        $this->line('xml_phone_area='.(($supplierContact['phone_area'] ?? '') !== '' ? $supplierContact['phone_area'] : '(empty)'));
        $this->line('xml_phone_number='.($supplierContact['phone_number'] ?? '—'));
        $this->line('ctcm_preview='.($formatted['ctcm_text'] ?? '—'));
        $this->line('ctcb_preview='.($formatted['ctcb_text'] ?? '—'));
        $this->line('contact_person_preview='.($formatted['contact_person_phone'] ?? '—'));
        $this->line('duplicate_country='.(($audit['duplicate_country'] ?? false) ? 'yes' : 'no'));
        $this->line('trunk_zero_after_country='.(($audit['trunk_zero_after_country'] ?? false) ? 'yes' : 'no'));
        $this->line('plus_inside_supplier_number='.(($audit['plus_inside_supplier_number'] ?? false) ? 'yes' : 'no'));
        $this->line('legacy_bad_contact_person='.($legacy['contact_person'] !== '' ? $legacy['contact_person'] : '—'));
        $this->line('legacy_bad_ctcm_ssr='.($legacy['ctcm_ssr'] !== '' ? $legacy['ctcm_ssr'] : '—'));
        $this->line('legacy_bad_ctcb_osi='.($legacy['ctcb_osi'] !== '' ? $legacy['ctcb_osi'] : '—'));
        $this->line('supplier_mutation_attempted=false');

        return self::SUCCESS;
    }
}
