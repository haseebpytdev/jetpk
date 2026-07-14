<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\Sabre\Diagnostics\SabreRevalidationPayloadStyleComparator;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Console\Command;

/**
 * Sprint 11K-J: Compare revalidation payload coverage across styles using stored offer/booking data only (no live Sabre).
 */
class SabreCompareRevalidatePayloadCoverageCommand extends Command
{
    protected $signature = 'sabre:compare-revalidate-payload-coverage
        {--booking= : Booking ID with Sabre offer snapshot}
        {--fixture : Use built-in test fixture draft when --booking is omitted}';

    protected $description = '[local/testing only] Compare Sabre revalidate payload coverage (no HTTP; scalar summary only)';

    public function handle(
        SabreBookingService $sabreBooking,
        SabreRevalidationPayloadStyleComparator $comparator,
    ): int {
        if (! SabreInspectGate::allowed()) {
            $this->components->error('This command only runs when APP_ENV is local or testing.');

            return self::FAILURE;
        }

        $draft = $this->resolveDraft($sabreBooking);
        if ($draft === null) {
            $this->components->error('Pass --booking={id} or --fixture for a built-in draft.');

            return self::FAILURE;
        }

        $report = $comparator->compareForDraft($draft);
        $this->line('diagnostic_only=true');
        $this->line('live_sabre_http=false');
        $this->line('report_version='.($report['report_version'] ?? ''));
        $this->line('active_config_style='.($report['active_config_style'] ?? ''));
        $this->line('recommended_production_default='.($report['recommended_production_default'] ?? ''));
        $this->line('production_default_unchanged='.(($report['production_default_unchanged'] ?? false) ? 'true' : 'false'));
        $this->newLine();

        $styles = is_array($report['styles'] ?? null) ? $report['styles'] : [];
        $rows = [];
        foreach ($styles as $style => $summary) {
            if (! is_array($summary)) {
                continue;
            }
            $rows[] = [
                'style' => (string) $style,
                'segment_count' => (string) ($summary['segment_count'] ?? '0'),
                'passenger_count' => (string) ($summary['passenger_count'] ?? '0'),
                'has_50itins' => $this->boolCell($summary['has_50itins'] ?? false),
                'has_data_sources' => $this->boolCell($summary['has_data_sources'] ?? false),
                'has_pcc' => $this->boolCell($summary['has_pcc'] ?? false),
                'has_price_request_information' => $this->boolCell($summary['has_price_request_information'] ?? false),
                'has_vendor_pref' => $this->boolCell($summary['has_vendor_pref'] ?? false),
                'selected_offer_context' => $this->boolCell($summary['selected_offer_context_present'] ?? false),
                'pricing_context' => $this->boolCell($summary['pricing_context_present'] ?? false),
            ];
        }

        $this->table(
            [
                'style',
                'segment_count',
                'passenger_count',
                'has_50itins',
                'has_data_sources',
                'has_pcc',
                'has_price_request_information',
                'has_vendor_pref',
                'selected_offer_context',
                'pricing_context',
            ],
            $rows,
        );

        $iatiStronger = is_array($report['iati_stronger_than_baseline_fields'] ?? null)
            ? $report['iati_stronger_than_baseline_fields']
            : [];
        $baselineStronger = is_array($report['baseline_stronger_than_iati_fields'] ?? null)
            ? $report['baseline_stronger_than_iati_fields']
            : [];
        $pricingStronger = is_array($report['pricing_context_stronger_than_baseline_fields'] ?? null)
            ? $report['pricing_context_stronger_than_baseline_fields']
            : [];

        $this->newLine();
        $this->line('iati_stronger_than_baseline_fields='.implode(',', $iatiStronger));
        $this->line('baseline_stronger_than_iati_fields='.implode(',', $baselineStronger));
        $this->line('pricing_context_stronger_than_baseline_fields='.implode(',', $pricingStronger));
        $this->line('iati_segment_count_delta='.(string) ($report['iati_segment_count_delta'] ?? 0));
        $this->line('safe_to_enable_iati_like_via_config=false');
        $this->line('hint=Override via SABRE_REVALIDATE_PAYLOAD_STYLE only after cert review; production default stays bfm_revalidate_v1.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveDraft(SabreBookingService $sabreBooking): ?array
    {
        $raw = $this->option('booking');
        if (is_string($raw) && trim($raw) !== '' && is_numeric($raw)) {
            $booking = Booking::query()->find((int) $raw);
            if ($booking === null) {
                $this->components->error('Booking not found.');

                return null;
            }

            return $this->resolveInternalDraftForBooking($booking, $sabreBooking);
        }

        if ($this->option('fixture')) {
            return $this->fixtureDraft();
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function fixtureDraft(): array
    {
        return [
            'provider' => SupplierProvider::Sabre->value,
            'selected_offer_id' => '11kj-fixture-offer',
            'supplier_offer_id' => '11kj-fixture-offer',
            'validating_carrier' => 'EK',
            'fare' => ['amount' => 450.0, 'currency' => 'USD'],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => '2026-09-01T10:00:00',
                    'arrival_at' => '2026-09-01T14:00:00',
                    'carrier' => 'EK',
                    'operating_airline_code' => 'EK',
                    'flight_number' => '615',
                    'booking_class' => 'K',
                    'fare_basis_code' => 'KLOWPK',
                ],
            ],
            'passengers' => [['type' => 'ADT']],
            '_sabre_shop_context' => [
                'itinerary_group_index' => 1,
                'itinerary_ref' => '10',
                'pricing_information_index' => 2,
                'leg_refs' => [3],
                'schedule_refs' => [9],
                'fare_component_refs' => [7],
                'pricing_information_ref' => 'pi-2',
            ],
            '_sabre_shop_identifiers' => [
                'pseudo_city_code' => 'TEST',
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveInternalDraftForBooking(Booking $booking, SabreBookingService $sabreBooking): ?array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $p = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($p !== SupplierProvider::Sabre->value) {
            return null;
        }

        $reflection = new \ReflectionClass($sabreBooking);
        $merge = $reflection->getMethod('mergePublicReviewSabreSnapshotFromBooking');
        $merge->setAccessible(true);
        $passengerData = $reflection->getMethod('passengerDataFromBooking');
        $passengerData->setAccessible(true);

        $snapshot = [];
        if (is_array($meta['normalized_offer_snapshot'] ?? null)) {
            $snapshot = $meta['normalized_offer_snapshot'];
        } elseif (is_array($meta['validated_offer_snapshot'] ?? null)) {
            $snapshot = $meta['validated_offer_snapshot'];
        } elseif (is_array($meta['flight_offer_snapshot'] ?? null)) {
            $snapshot = $meta['flight_offer_snapshot'];
        }
        $snapshot = $merge->invoke($sabreBooking, $booking, $snapshot);
        $draft = $sabreBooking->prepareBookingPayload($snapshot, $passengerData->invoke($sabreBooking, $booking));
        if (! is_array($draft) || ($draft['_valid'] ?? false) !== true) {
            return null;
        }
        unset($draft['_valid']);

        return $draft;
    }

    protected function boolCell(mixed $value): string
    {
        return $value === true ? 'true' : 'false';
    }
}
