<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Console\Command;

/**
 * [local/testing only] POST matrix across Offers-shop revalidate paths and payload styles. Prints a capped scalar table
 * only (no raw request/response, no Authorization, PCC, passenger/contact fields). Does not call Trip Orders
 * {@code createBooking} or ticketing.
 *
 * B19: Compares {@code /v4/offers/shop/revalidate} and {@code /v5/offers/shop/revalidate} against the same styles, then
 * probes {@code /v4/shop/flights/revalidate} privately to decide whether to print {@code recommended_revalidate_path}.
 *
 * B20: optional {@code --show-response-digest} appends safe per-row digest columns (top-level keys, body empty flag,
 * candidate count; no raw response).
 */
class SabreCompareRevalidateStylesCommand extends Command
{
    /** @var list<string> */
    public const MATRIX_PATHS = [
        '/v4/offers/shop/revalidate',
        '/v5/offers/shop/revalidate',
    ];

    /** @var list<string> */
    public const COMPARE_STYLES = [
        'bfm_revalidate_v1',
        'bfm_revalidate_with_pricing_context',
        'client_gds_revalidate_v1',
        'bfm_revalidate_original_like',
    ];

    public const BASELINE_BFM_PATH = '/v4/shop/flights/revalidate';

    protected $signature = 'sabre:compare-revalidate-styles {--booking= : Booking ID} {--show-response-digest : Append safe response-structure digest per matrix row (no raw body)}';

    protected $description = '[local/testing only] Matrix Offers revalidate paths × styles (safe summary; no createBooking)';

    public function handle(SabreBookingService $sabreBooking): int
    {
        if (! SabreInspectGate::allowed()) {
            $this->components->error('This command only runs when APP_ENV is local or testing.');

            return self::FAILURE;
        }

        $raw = $this->option('booking');
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            $this->components->error('Pass --booking={id} with a numeric booking id.');

            return self::FAILURE;
        }

        $booking = Booking::query()->find((int) $raw);
        if ($booking === null) {
            $this->components->error('Booking not found.');

            return self::FAILURE;
        }

        $apiDraft = $this->resolveInternalDraftForBooking($booking, $sabreBooking);
        if ($apiDraft === null) {
            $this->line('booking_id='.$booking->id);
            $this->line('error=booking_not_sabre_or_invalid_offer');

            return self::SUCCESS;
        }

        $connection = $this->resolveConnection($booking);
        if ($connection === null) {
            $this->components->error('No Sabre supplier connection resolvable for this booking.');

            return self::FAILURE;
        }

        $this->line('Local matrix only: live Sabre revalidate HTTP; no Trip Orders createBooking; no ticketing.');
        $this->line('booking_id='.$booking->id);
        $this->line('connection_id='.$connection->id);
        $this->newLine();

        $showDigest = (bool) $this->option('show-response-digest');

        /** @var list<array<string, string>> $tableRows */
        $tableRows = [];
        $v4OffersSuccesses = 0;

        foreach (self::MATRIX_PATHS as $path) {
            foreach (self::COMPARE_STYLES as $style) {
                $outcome = $sabreBooking->runRevalidationBeforeBooking($apiDraft, $connection, $style, $path);
                $tableRows[] = $this->formatMatrixRow($path, $style, $outcome, $showDigest);
                if ($path === '/v4/offers/shop/revalidate' && ($outcome['success'] ?? false) === true) {
                    $v4OffersSuccesses++;
                }
            }
        }

        $headers = [
            'path',
            'style',
            'http_status',
            'revalidation_success',
            'safe_error_messages',
            'changed_from_27131',
            'has_revalidated_fare',
            'has_revalidation_reference',
            'has_revalidated_currency',
        ];
        if ($showDigest) {
            $headers[] = 'response_top_level_keys';
            $headers[] = 'response_body_empty';
            $headers[] = 'candidate_count';
        }

        $this->table($headers, $tableRows);

        $bfmSuccesses = 0;
        foreach (self::COMPARE_STYLES as $style) {
            $outcome = $sabreBooking->runRevalidationBeforeBooking(
                $apiDraft,
                $connection,
                $style,
                self::BASELINE_BFM_PATH,
            );
            if (($outcome['success'] ?? false) === true) {
                $bfmSuccesses++;
            }
        }

        $this->newLine();
        $this->line('baseline_path='.self::BASELINE_BFM_PATH);
        $this->line('baseline_successful_styles='.$bfmSuccesses.'/'.count(self::COMPARE_STYLES));
        $this->line('offers_v4_successful_styles='.$v4OffersSuccesses.'/'.count(self::COMPARE_STYLES));

        if ($v4OffersSuccesses > 0 && $v4OffersSuccesses >= $bfmSuccesses) {
            $this->line('recommended_revalidate_path=/v4/offers/shop/revalidate');
        } else {
            $this->line('recommended_revalidate_path=(none — offers v4 did not beat baseline success count)');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @return array<string, string>
     */
    protected function formatMatrixRow(string $path, string $style, array $outcome, bool $showDigest = false): array
    {
        $digest = is_array($outcome['linkage_digest'] ?? null) ? $outcome['linkage_digest'] : [];
        $err = is_array($outcome['error_digest'] ?? null) ? $outcome['error_digest'] : [];
        $msgs = array_slice((array) ($err['response_error_messages'] ?? []), 0, 6);
        $safeMsgs = implode(' | ', array_map(static fn ($m): string => substr((string) $m, 0, 120), $msgs));
        if ($safeMsgs === '') {
            $codes = array_slice((array) ($err['response_error_codes'] ?? []), 0, 4);
            $safeMsgs = implode(' | ', array_map(static fn ($c): string => substr((string) $c, 0, 32), $codes));
        }
        if (strlen($safeMsgs) > 260) {
            $safeMsgs = substr($safeMsgs, 0, 260).'…';
        }

        $http = $outcome['http_status'] ?? null;

        $row = [
            'path' => $path,
            'style' => $style,
            'http_status' => $http === null ? '—' : (string) $http,
            'revalidation_success' => (($outcome['success'] ?? false) ? 'true' : 'false'),
            'safe_error_messages' => $safeMsgs !== '' ? $safeMsgs : '—',
            'changed_from_27131' => (($outcome['changed_from_typical_27131_failure'] ?? false) ? 'true' : 'false'),
            'has_revalidated_fare' => (($digest['has_revalidated_fare'] ?? false) ? 'true' : 'false'),
            'has_revalidation_reference' => (($digest['has_revalidation_reference'] ?? false) ? 'true' : 'false'),
            'has_revalidated_currency' => (($digest['has_revalidated_currency'] ?? false) ? 'true' : 'false'),
        ];

        if ($showDigest) {
            $rs = is_array($outcome['response_structure'] ?? null) ? $outcome['response_structure'] : [];
            $keys = substr((string) ($rs['top_level_keys'] ?? ''), 0, 200);
            $row['response_top_level_keys'] = $keys !== '' ? $keys : '—';
            $row['response_body_empty'] = (string) ($rs['empty_body'] ?? '—');
            $row['candidate_count'] = (string) ($rs['candidate_count'] ?? '0');
        }

        return $row;
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

    protected function resolveConnection(Booking $booking): ?SupplierConnection
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $cid = (int) ($meta['supplier_connection_id'] ?? 0);
        if ($cid > 0) {
            $c = SupplierConnection::query()->find($cid);
            if ($c !== null && $c->provider === SupplierProvider::Sabre) {
                return $c;
            }
        }

        return SupplierConnection::query()
            ->where('provider', SupplierProvider::Sabre)
            ->orderBy('id')
            ->first();
    }
}
