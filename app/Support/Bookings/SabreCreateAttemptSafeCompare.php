<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\SupplierBookingAttempt;

/**
 * E3: Safe-only comparison of Passenger Records create attempt summaries (no raw payloads or PII).
 */
final class SabreCreateAttemptSafeCompare
{
    /** @var list<string> */
    public const COMPARE_KEYS = [
        'create_endpoint_path',
        'create_payload_style',
        'create_segment_count',
        'create_segments_summary',
        'create_passenger_count',
        'create_contact_present',
        'create_received_from_present',
        'create_ticketing_disabled',
        'create_post_ticketing_action',
        'create_price_quote_present',
        'create_host_command_style',
        'create_segment_source',
        'create_route_continuity',
        'create_chronology_gaps',
        'create_snapshot_segment_count',
        'create_segment_order_repaired',
        'create_date_repair_applied',
        'payload_schema',
        'booking_schema',
        'segment_count',
        'passenger_count',
        'http_status',
        'error_code',
        'response_error_codes',
        'response_error_messages',
        'host_warning_sabre_codes',
        'host_warning_messages_truncated',
        'application_results_status',
        'application_results_incomplete',
    ];

    /**
     * @param  list<int>  $attemptIds
     * @return array<string, mixed>
     */
    public function compareAttempts(array $attemptIds): array
    {
        $rows = [];
        foreach ($attemptIds as $id) {
            $attemptId = (int) $id;
            if ($attemptId < 1) {
                continue;
            }
            $attempt = SupplierBookingAttempt::query()->find($attemptId);
            if ($attempt === null) {
                $rows[] = [
                    'attempt_id' => $attemptId,
                    'found' => false,
                ];

                continue;
            }
            $rows[] = $this->rowFromAttempt($attempt);
        }

        return [
            'attempts' => $rows,
            'field_diff' => $this->diffComparableRows($rows),
        ];
    }

    /**
     * @param  list<int>  $bookingIds
     * @return array<string, mixed>
     */
    public function compareLatestCreateAttemptsForBookings(array $bookingIds): array
    {
        $attemptIds = [];
        foreach ($bookingIds as $bookingId) {
            $bid = (int) $bookingId;
            if ($bid < 1) {
                continue;
            }
            $createAttempts = SupplierBookingAttempt::query()
                ->where('booking_id', $bid)
                ->where('provider', SupplierProvider::Sabre->value)
                ->where('action', 'create_pnr')
                ->orderByDesc('id')
                ->get();
            $meaningful = SupplierBookingAttemptResolution::resolveLatestMeaningfulCreateAttempt($createAttempts);
            if ($meaningful !== null) {
                $attemptIds[] = (int) $meaningful->id;
            }
        }

        $out = $this->compareAttempts($attemptIds);
        $out['booking_ids'] = array_values(array_map('intval', $bookingIds));

        return $out;
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     * @return array<string, mixed>
     */
    public function extractComparableSlice(array $safeSummary): array
    {
        $slice = [];
        foreach (self::COMPARE_KEYS as $key) {
            if (! array_key_exists($key, $safeSummary)) {
                continue;
            }
            $value = $safeSummary[$key];
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $slice[$key] = $value;
        }

        return $slice;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rowFromAttempt(SupplierBookingAttempt $attempt): array
    {
        $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];

        return array_merge([
            'attempt_id' => $attempt->id,
            'booking_id' => $attempt->booking_id,
            'found' => true,
            'status' => (string) $attempt->status,
            'action' => (string) $attempt->action,
            'error_code' => $attempt->error_code,
        ], $this->extractComparableSlice($safe));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    protected function diffComparableRows(array $rows): array
    {
        $found = array_values(array_filter($rows, static fn (array $r): bool => ($r['found'] ?? false) === true));
        if (count($found) < 2) {
            return ['comparable' => false, 'reason' => 'need_at_least_two_found_attempts'];
        }

        $diffs = [];
        foreach (self::COMPARE_KEYS as $key) {
            $values = [];
            foreach ($found as $row) {
                if (! array_key_exists($key, $row)) {
                    continue;
                }
                $values[(string) $row['attempt_id']] = $row[$key];
            }
            if (count($values) < 2) {
                continue;
            }
            $encoded = array_map(static fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE), $values);
            if (count(array_unique($encoded)) > 1) {
                $diffs[$key] = $values;
            }
        }

        return [
            'comparable' => true,
            'diff_count' => count($diffs),
            'diffs' => $diffs,
        ];
    }
}
