<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Support\Sabre\SabreCapabilityPosture;

/**
 * Phase F2: Safe admin/staff labels for PNR retrieve/sync and cancel-readiness sidecar
 * (no raw getBooking JSON, bookingId values, or PII).
 */
class PnrItinerarySyncSafetyPresenter
{
    /**
     * @return array{
     *     show_panel: bool,
     *     retrieve_result_label: string,
     *     sync_status_label: string,
     *     reason_label: ?string,
     *     cancel_eligible_label: string,
     *     is_ticketed_label: string,
     *     ticket_numbers_label: string,
     *     booking_id_label: string,
     *     live_cancel_label: string,
     *     gds_cancel_posture_label: string,
     *     gds_ticketing_posture_label: string,
     *     ndc_posture_label: string,
     *     sabre_pnr_label: ?string,
     *     airline_locator_label: ?string,
     *     airline_locator_display: string,
     *     verification_note: ?string,
     *     ticketing_status_label: string,
     *     segments: list<array{
     *         leg: int,
     *         route_label: string,
     *         flight_label: string,
     *         segment_status: string,
     *         status_label: string,
     *         status_class: string
     *     }>
     * }
     */
    public static function forBooking(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        $isSabre = $provider === SupplierProvider::Sabre->value;

        $pnr = trim((string) ($booking->pnr ?? ''));
        $supplierReference = trim((string) ($booking->supplier_reference ?? ''));
        $syncSidecar = is_array($meta['pnr_itinerary_sync'] ?? null) ? $meta['pnr_itinerary_sync'] : [];
        $snapshot = is_array($meta['pnr_itinerary_snapshot'] ?? null) ? $meta['pnr_itinerary_snapshot'] : [];
        $hasSyncSidecar = $syncSidecar !== [];
        $hasSnapshot = is_array($snapshot['segments'] ?? null) && $snapshot['segments'] !== [];

        $showPanel = $isSabre && (
            $pnr !== ''
            || $supplierReference !== ''
            || $hasSyncSidecar
            || $hasSnapshot
        );

        if (! $showPanel) {
            return self::emptyResult(false);
        }

        $syncStatus = strtolower(trim((string) ($syncSidecar['status'] ?? '')));
        $reasonCode = trim((string) ($syncSidecar['reason_code'] ?? ''));
        if ($reasonCode === '' && $syncStatus !== '' && $syncStatus !== 'synced') {
            $reasonCode = $syncStatus;
        }

        $cancelEnabled = (bool) config('suppliers.sabre.cancel_enabled', false);
        $cancelLiveEnabled = (bool) config('suppliers.sabre.cancel_live_call_enabled', false);
        $posture = new SabreCapabilityPosture;
        $airlineLocator = is_string($syncSidecar['airline_locator_value'] ?? null)
            ? strtoupper(trim($syncSidecar['airline_locator_value']))
            : null;
        $airlineLocatorPresent = ($syncSidecar['airline_locator_present'] ?? false) === true && $airlineLocator !== null;
        $sidecarPnr = is_string($syncSidecar['pnr'] ?? null) ? strtoupper(trim($syncSidecar['pnr'])) : '';
        $sabrePnrLabel = $sidecarPnr !== '' ? $sidecarPnr : ($pnr !== '' ? strtoupper($pnr) : null);

        return [
            'show_panel' => true,
            'retrieve_result_label' => self::retrieveResultLabel($syncStatus),
            'sync_status_label' => self::syncStatusLabel($syncStatus),
            'reason_label' => $reasonCode !== '' ? self::reasonLabel($reasonCode) : null,
            'cancel_eligible_label' => self::triStateBoolLabel(
                array_key_exists('is_cancelable', $syncSidecar) ? self::nullableBool($syncSidecar['is_cancelable']) : null,
                'Yes',
                'No',
            ),
            'is_ticketed_label' => self::triStateBoolLabel(
                array_key_exists('is_ticketed', $syncSidecar) ? self::nullableBool($syncSidecar['is_ticketed']) : null,
                'Yes',
                'No',
            ),
            'ticket_numbers_label' => self::triStateBoolLabel(
                array_key_exists('ticket_numbers_present', $syncSidecar)
                    ? (bool) $syncSidecar['ticket_numbers_present']
                    : null,
                'Present',
                'Not present',
            ),
            'booking_id_label' => self::triStateBoolLabel(
                array_key_exists('booking_id_present', $syncSidecar)
                    ? (bool) $syncSidecar['booking_id_present']
                    : null,
                'Present',
                'Not present',
            ),
            'live_cancel_label' => ($cancelEnabled && $cancelLiveEnabled) ? 'Enabled' : 'Disabled',
            'gds_cancel_posture_label' => $posture->architectureDisplayLabel($posture->cancelPosture()),
            'gds_ticketing_posture_label' => $posture->architectureDisplayLabel($posture->ticketingPosture()),
            'ndc_posture_label' => $posture->ndcPosture()['summary_label'],
            'sabre_pnr_label' => $sabrePnrLabel,
            'airline_locator_label' => $airlineLocatorPresent ? $airlineLocator : null,
            'airline_locator_display' => $airlineLocatorPresent ? $airlineLocator : 'Not recorded yet',
            'verification_note' => self::verificationNote($syncStatus, $syncSidecar),
            'ticketing_status_label' => self::ticketingStatusLabel($syncSidecar),
            'segments' => self::buildSegments($snapshot),
        ];
    }

    /**
     * @return array{
     *     show_panel: bool,
     *     retrieve_result_label: string,
     *     sync_status_label: string,
     *     reason_label: ?string,
     *     cancel_eligible_label: string,
     *     is_ticketed_label: string,
     *     ticket_numbers_label: string,
     *     booking_id_label: string,
     *     live_cancel_label: string,
     *     gds_cancel_posture_label: string,
     *     gds_ticketing_posture_label: string,
     *     ndc_posture_label: string,
     *     sabre_pnr_label: ?string,
     *     airline_locator_label: ?string,
     *     airline_locator_display: string,
     *     verification_note: ?string,
     *     ticketing_status_label: string,
     *     segments: list<array{
     *         leg: int,
     *         route_label: string,
     *         flight_label: string,
     *         segment_status: string,
     *         status_label: string,
     *         status_class: string
     *     }>
     * }
     */
    protected static function emptyResult(bool $showPanel): array
    {
        return [
            'show_panel' => $showPanel,
            'retrieve_result_label' => 'Not attempted',
            'sync_status_label' => 'Not synced',
            'reason_label' => null,
            'cancel_eligible_label' => 'Unknown',
            'is_ticketed_label' => 'Unknown',
            'ticket_numbers_label' => 'Unknown',
            'booking_id_label' => 'Unknown',
            'live_cancel_label' => 'Disabled',
            'gds_cancel_posture_label' => '',
            'gds_ticketing_posture_label' => '',
            'ndc_posture_label' => '',
            'sabre_pnr_label' => null,
            'airline_locator_label' => null,
            'airline_locator_display' => 'Not recorded yet',
            'verification_note' => null,
            'ticketing_status_label' => 'Unknown',
            'segments' => [],
        ];
    }

    protected static function retrieveResultLabel(string $syncStatus): string
    {
        return match ($syncStatus) {
            'synced' => 'Success',
            'partial_resource_unavailable' => 'Partial / needs manual verification',
            '' => 'Not attempted',
            default => 'Failed',
        };
    }

    protected static function syncStatusLabel(string $syncStatus): string
    {
        if ($syncStatus === '') {
            return 'Not synced';
        }

        return match ($syncStatus) {
            'synced' => 'Synced',
            'partial_resource_unavailable' => 'Partial verification (resource unavailable)',
            'retrieve_failed' => 'Retrieve failed',
            'blocked_resource_unavailable' => 'Blocked (resource unavailable)',
            'blocked_segment_status' => 'Blocked (segment status)',
            'unmappable' => 'Not mappable',
            default => ucfirst(str_replace('_', ' ', $syncStatus)),
        };
    }

    protected static function reasonLabel(string $reasonCode): string
    {
        return match ($reasonCode) {
            'retrieve_failed' => 'PNR retrieve failed',
            'get_booking_empty' => 'Empty getBooking response',
            'partial_resource_unavailable' => 'Sabre reported resource unavailable with partial verification data',
            'blocked_resource_unavailable' => 'Sabre reported resource unavailable',
            'blocked_segment_status' => 'One or more segments have a non-confirmable status',
            'unmappable' => 'Itinerary could not be mapped from getBooking',
            'sabre_auth_failed' => 'Sabre authentication failed',
            'sabre_connection_failed', 'sabre_connection_missing' => 'Sabre connection unavailable',
            'sabre_request_failed' => 'Sabre request failed',
            'booking_missing_pnr' => 'Booking has no PNR',
            default => ucfirst(str_replace('_', ' ', $reasonCode)),
        };
    }

    /**
     * @param  array<string, mixed>  $syncSidecar
     */
    protected static function verificationNote(string $syncStatus, array $syncSidecar): ?string
    {
        if ($syncStatus !== 'partial_resource_unavailable') {
            return null;
        }

        if (($syncSidecar['airline_locator_present'] ?? false) === true) {
            return 'Carrier locator detected, but full itinerary was not synced. Verify with airline/carrier before ticketing.';
        }

        return 'Partial verification data detected, but full itinerary was not synced. Verify manually before ticketing.';
    }

    /**
     * @param  array<string, mixed>  $syncSidecar
     */
    protected static function ticketingStatusLabel(array $syncSidecar): string
    {
        if (array_key_exists('is_ticketed', $syncSidecar)) {
            $ticketed = self::nullableBool($syncSidecar['is_ticketed']);
            if ($ticketed === true) {
                return 'Ticketed';
            }
            if ($ticketed === false) {
                return 'Pending / not ticketed';
            }
        }

        if (($syncSidecar['ticket_numbers_present'] ?? false) === true) {
            return 'Ticket numbers present';
        }

        return 'Pending / not ticketed';
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<array{
     *     leg: int,
     *     route_label: string,
     *     flight_label: string,
     *     segment_status: string,
     *     status_label: string,
     *     status_class: string
     * }>
     */
    protected static function buildSegments(array $snapshot): array
    {
        $rows = is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : [];
        $out = [];
        $leg = 1;

        foreach (array_slice($rows, 0, 8) as $segment) {
            if (! is_array($segment)) {
                continue;
            }

            $origin = strtoupper(trim((string) ($segment['origin'] ?? '')));
            $destination = strtoupper(trim((string) ($segment['destination'] ?? '')));
            $airline = strtoupper(trim((string) ($segment['airline_code'] ?? '')));
            $flightNumber = trim((string) ($segment['flight_number'] ?? ''));
            $status = strtoupper(trim((string) ($segment['segment_status'] ?? '')));
            $statusMeta = self::segmentStatusMeta($status);

            $routeLabel = $origin !== '' && $destination !== ''
                ? $origin.'–'.$destination
                : ($origin !== '' ? $origin : ($destination !== '' ? $destination : '—'));

            $flightLabel = $airline !== '' && $flightNumber !== ''
                ? $airline.$flightNumber
                : ($airline !== '' ? $airline : ($flightNumber !== '' ? $flightNumber : '—'));

            $out[] = [
                'leg' => $leg,
                'route_label' => $routeLabel,
                'flight_label' => $flightLabel,
                'segment_status' => $status !== '' ? $status : '—',
                'status_label' => $statusMeta['label'],
                'status_class' => $statusMeta['class'],
            ];
            $leg++;
        }

        return $out;
    }

    /**
     * @return array{label: string, class: string}
     */
    protected static function segmentStatusMeta(string $status): array
    {
        return match ($status) {
            'HK' => ['label' => 'Confirmed', 'class' => 'success'],
            'TK' => ['label' => 'Schedule change', 'class' => 'warning'],
            'UC', 'UN', 'NO' => ['label' => 'Unable to confirm', 'class' => 'danger'],
            'HX', 'XX' => ['label' => 'Cancelled', 'class' => 'danger'],
            'NN', 'PN' => ['label' => 'Pending', 'class' => 'warning'],
            '' => ['label' => 'Unknown', 'class' => 'secondary'],
            default => ['label' => $status, 'class' => 'secondary'],
        };
    }

    protected static function triStateBoolLabel(?bool $value, string $trueLabel, string $falseLabel): string
    {
        if ($value === true) {
            return $trueLabel;
        }
        if ($value === false) {
            return $falseLabel;
        }

        return 'Unknown';
    }

    protected static function nullableBool(mixed $value): ?bool
    {
        if ($value === true || $value === false) {
            return $value;
        }
        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }
        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        return null;
    }
}
