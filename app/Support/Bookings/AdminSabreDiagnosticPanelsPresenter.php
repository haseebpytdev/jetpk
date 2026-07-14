<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Services\Suppliers\Sabre\Diagnostics\SabreBookingContinuityAuditor;
use App\Support\Sabre\SabreHostSellClassifier;
use App\Support\Sabre\SabreHostSellReshopComparator;
use App\Support\Sabre\SabreHostSellRetryGuard;
use App\Support\Sabre\SabrePnrLaneDiagnostics;
use App\Support\Sabre\SabreReadinessReasonPresenter;
use App\Support\Suppliers\SupplierActionCode;
use App\Support\Suppliers\SupplierActionStrategyDigest;
use App\Support\Suppliers\SupplierActionStrategySelector;
use App\Support\Suppliers\SupplierPnrFlagGate;
use App\Support\Suppliers\SupplierPnrValidationSummary;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Admin/staff booking detail: snapshot-only Sabre diagnostic panels (Sprint 4 / 11A / 11K-E).
 * No live Sabre HTTP, no PNR create, no raw payloads or PII.
 */
final class AdminSabreDiagnosticPanelsPresenter
{
    public function __construct(
        private SabreReadinessReasonPresenter $readinessReasons = new SabreReadinessReasonPresenter,
    ) {}

    /**
     * Admin/staff booking detail: safe Sabre PNR readiness + attempt diagnostics (Sprint 4; no raw payloads).
     *
     * @return array{show: bool, title: string, rows: list<array{label: string, value: string, badge: ?string}>}
     */
    public function pnrReadinessPanel(Booking $booking): array
    {
        $empty = ['show' => false, 'title' => 'Sabre PNR Readiness', 'rows' => []];

        try {
            $layers = $this->collectSabreDiagnosticLayers($booking);
            if (! $layers['is_sabre']) {
                return $empty;
            }

            $fields = $this->adminSafeSabreDiagnosticFieldsForOutput($layers['merged']);
            $fields = array_merge(
                $fields,
                $this->adminSafeMultiSegmentReadinessFields(
                    app(SabrePnrCertificationSupport::class)->buildMultiSegmentPnrReadinessDiagnostics($booking)
                ),
                $this->adminSafeHostSellRejectFields($layers['merged']),
                $this->adminSafeHostSellPendingNnFields($layers['merged']),
                $this->adminSafeCreatePayloadHaltPolicyFields($layers['merged']),
            );
            $rows = $this->sabrePnrReadinessRowsFromFields(
                $fields,
                $booking,
                $layers['last_attempt_at'],
                $layers['create_attempt_label'] ?? null,
                $layers['sync_attempt_label'] ?? null,
                $layers['ticketing_attempt_label'] ?? null,
            );
            $rows = array_merge($rows, $this->supplierStrategyPanelRows($booking));

            return [
                'show' => true,
                'title' => 'Sabre PNR Readiness',
                'rows' => $rows,
            ];
        } catch (\Throwable $e) {
            Log::warning('sabre_pnr_readiness_panel_unavailable', [
                'booking_id' => $booking->id,
                'exception' => $e::class,
                'message' => Str::limit($e->getMessage(), 120, ''),
            ]);

            return $empty;
        }
    }

    /**
     * D2F-D2: read-only display of stored checkout host classification (no re-classify, no Sabre calls).
     *
     * @return array{
     *     show: bool,
     *     fields: array<string, string>,
     *     signal_badges: list<string>,
     *     disclaimer: string
     * }
     */
    public function hostClassificationPanel(Booking $booking): array
    {
        $disclaimer = 'Advisory only. This classification was saved at checkout. It does not enable or disable Retry buttons and does not trigger automated Sabre actions.';
        $empty = ['show' => false, 'fields' => [], 'signal_badges' => [], 'disclaimer' => $disclaimer];

        try {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $checkout = is_array($meta['sabre_checkout_outcome'] ?? null) ? $meta['sabre_checkout_outcome'] : [];
            $classification = $checkout['sabre_host_classification'] ?? null;

            if (! is_array($classification) || $classification === []) {
                return $empty;
            }

            $parsed = $this->adminSafeSabreHostClassificationFields($classification);
            if ($parsed['fields'] === []) {
                return $empty;
            }

            return [
                'show' => true,
                'fields' => $parsed['fields'],
                'signal_badges' => $parsed['signal_badges'],
                'disclaimer' => $disclaimer,
            ];
        } catch (\Throwable $e) {
            Log::warning('sabre_host_classification_panel_unavailable', [
                'booking_id' => $booking->id,
                'exception' => $e::class,
                'message' => Str::limit($e->getMessage(), 120, ''),
            ]);

            return $empty;
        }
    }

    /**
     * SABRE-GDS-HOST-SELL-DIAGNOSTICS: compact host sell panel for Sabre GDS bookings only.
     *
     * @return array{
     *     show: bool,
     *     title: string,
     *     lane: string,
     *     rows: list<array{label: string, value: string}>,
     *     retry_blocked_for_same_offer: bool,
     *     reshop_recommended: bool
     * }
     */
    public function hostSellDiagnosticsPanel(Booking $booking): array
    {
        $empty = [
            'show' => false,
            'title' => 'Sabre Host Sell Diagnostics',
            'lane' => '',
            'rows' => [],
            'retry_blocked_for_same_offer' => false,
            'reshop_recommended' => false,
        ];

        try {
            if (! app(SupplierLifecycleContextResolver::class)->isHandler(
                $booking,
                SupplierLifecycleContextResolver::HANDLER_SABRE_GDS,
            )) {
                return $empty;
            }

            $meta = is_array($booking->meta) ? $booking->meta : [];
            $diagnostics = is_array($meta['sabre_host_sell_diagnostics'] ?? null)
                ? $meta['sabre_host_sell_diagnostics']
                : [];

            if ($diagnostics === []) {
                $checkout = is_array($meta['sabre_checkout_outcome'] ?? null) ? $meta['sabre_checkout_outcome'] : [];
                $classification = is_array($checkout['sabre_host_classification'] ?? null)
                    ? $checkout['sabre_host_classification']
                    : [];
                if ($classification === [] && ($checkout['live_call_attempted'] ?? false) !== true) {
                    return $empty;
                }
                $diagnostics = array_filter([
                    'safe_reason_code' => $classification['safe_reason_code'] ?? $checkout['error_code'] ?? null,
                    'airline_segment_statuses' => isset($checkout['airline_segment_status'])
                        ? [strtoupper((string) $checkout['airline_segment_status'])]
                        : null,
                    'flight_numbers' => $checkout['affected_flight_numbers'] ?? null,
                    'retry_policy' => $classification['retry_policy'] ?? null,
                    'recommended_admin_action' => $classification['recommended_admin_action'] ?? $classification['admin_summary'] ?? null,
                    'fingerprint_hash' => data_get($checkout, 'sabre_host_rejection_fingerprint.fingerprint_hash'),
                    'pnr_lane' => SabrePnrLaneDiagnostics::detectPrimaryLane($booking),
                ], static fn ($v) => $v !== null && $v !== '' && $v !== []);
            }

            if ($diagnostics === []) {
                return $empty;
            }

            $lane = (string) ($diagnostics['pnr_lane'] ?? SabrePnrLaneDiagnostics::detectPrimaryLane($booking));
            $fingerprint = is_array($meta['sabre_host_sell_fingerprint_latest'] ?? null)
                ? $meta['sabre_host_sell_fingerprint_latest']
                : [];
            $retryGuard = SabreHostSellRetryGuard::shouldBlockFromDiagnostics(array_merge(
                $diagnostics,
                ['occurrence_count' => $fingerprint['occurrence_count'] ?? 1],
            ));

            $reshopCompare = null;
            $failedSnap = is_array($meta['failed_offer_snapshot_for_reshop'] ?? null)
                ? $meta['failed_offer_snapshot_for_reshop']
                : null;
            $freshSnap = is_array($meta['normalized_offer_snapshot'] ?? null)
                ? $meta['normalized_offer_snapshot']
                : (is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : null);
            if (is_array($failedSnap) && is_array($freshSnap)) {
                $reshopCompare = SabreHostSellReshopComparator::compare($failedSnap, $freshSnap);
            }

            $segmentStatuses = is_array($diagnostics['airline_segment_statuses'] ?? null)
                ? implode(', ', array_map('strval', $diagnostics['airline_segment_statuses']))
                : '-';
            $flights = is_array($diagnostics['flight_numbers'] ?? null)
                ? implode(', ', array_map('strval', array_slice($diagnostics['flight_numbers'], 0, 8)))
                : '-';

            $rows = array_values(array_filter([
                ['label' => 'PNR lane', 'value' => SabrePnrLaneDiagnostics::laneLabel($lane)],
                ['label' => 'Safe reason', 'value' => $this->formatSabreHostClassificationDisplayScalar($diagnostics['safe_reason_code'] ?? null)],
                ['label' => 'Segment statuses', 'value' => $segmentStatuses],
                ['label' => 'Affected flights', 'value' => $flights],
                ['label' => 'Retry policy', 'value' => $this->formatSabreHostRetryPolicyAdvisory((string) ($diagnostics['retry_policy'] ?? ''))],
                ['label' => 'Recommended admin action', 'value' => $this->formatSabreHostClassificationDisplayScalar($diagnostics['recommended_admin_action'] ?? null)],
                ['label' => 'Fingerprint hash', 'value' => Str::limit((string) ($fingerprint['fingerprint_hash'] ?? $diagnostics['fingerprint_hash'] ?? '-'), 16, '...')],
                ['label' => 'Occurrence count', 'value' => (string) ((int) ($fingerprint['occurrence_count'] ?? 0) ?: '-')],
                ['label' => 'Same-offer retry blocked', 'value' => $retryGuard ? 'yes' : 'no'],
                ['label' => 'Re-shop recommended', 'value' => ($reshopCompare['reshop_recommended'] ?? self::reshopRecommendedForReason((string) ($diagnostics['safe_reason_code'] ?? ''))) ? 'yes' : 'no'],
            ], static fn (array $row): bool => ($row['value'] ?? '-') !== '-'));

            return [
                'show' => $rows !== [],
                'title' => 'Sabre Host Sell Diagnostics',
                'lane' => $lane,
                'rows' => $rows,
                'retry_blocked_for_same_offer' => $retryGuard,
                'reshop_recommended' => (bool) ($reshopCompare['reshop_recommended'] ?? self::reshopRecommendedForReason((string) ($diagnostics['safe_reason_code'] ?? ''))),
            ];
        } catch (\Throwable $e) {
            Log::warning('sabre_host_sell_diagnostics_panel_unavailable', [
                'booking_id' => $booking->id,
                'exception' => $e::class,
                'message' => Str::limit($e->getMessage(), 120, ''),
            ]);

            return $empty;
        }
    }

    protected static function reshopRecommendedForReason(string $safeReasonCode): bool
    {
        return in_array(strtolower(trim($safeReasonCode)), [
            SabreHostSellClassifier::OUTCOME_HOST_SELL_REJECTED_UC,
            SabreHostSellClassifier::OUTCOME_HOST_NO_ACTION_OR_REJECTED,
            SabreHostSellClassifier::OUTCOME_HOST_HALT_ON_STATUS,
            SabreHostSellClassifier::OUTCOME_HOST_NEED_NEED_STATUS,
        ], true);
    }

    /**
     * Sprint 11K-E: passive Sabre continuity audit + host outcome overlay for admin/staff booking detail (read-only).
     *
     * @return array{
     *     show: bool,
     *     unavailable: bool,
     *     unavailable_message: string,
     *     title: string,
     *     disclaimer: string,
     *     summary_rows: list<array{label: string, value: string, hint: ?string}>,
     *     source_present_rows: list<array{label: string, value: string}>,
     *     continuity_field_rows: list<array{label: string, value: string}>
     * }
     */
    /**
     * @param  array<string, mixed>  $checkout
     * @param  array<string, mixed>  $meta
     * @return list<array{label: string, value: string, hint: string|null}>
     */
    public function buildSabreHostRejectionFingerprintPanelRows(array $checkout, array $meta = []): array
    {
        $stored = is_array($checkout['sabre_host_rejection_fingerprint'] ?? null)
            ? $checkout['sabre_host_rejection_fingerprint']
            : [];
        $match = is_array($meta['host_rejection_fingerprint_match'] ?? null)
            ? $meta['host_rejection_fingerprint_match']
            : (is_array($checkout['host_rejection_fingerprint_match'] ?? null)
                ? $checkout['host_rejection_fingerprint_match']
                : []);

        if ($stored === [] && $match === []) {
            return [];
        }

        $rows = [];
        if ($stored !== []) {
            $rows[] = [
                'label' => 'Host rejection fingerprint stored',
                'value' => 'Yes',
                'hint' => null,
            ];
            $rows[] = [
                'label' => 'Fingerprint host error family',
                'value' => $this->formatSabreContinuityCode((string) ($stored['host_error_family'] ?? '')),
                'hint' => null,
            ];
            $rows[] = [
                'label' => 'Fingerprint safe reason code',
                'value' => $this->formatSabreHostClassificationDisplayScalar($stored['safe_reason_code'] ?? null),
                'hint' => null,
            ];
            $hash = trim((string) ($stored['fingerprint_hash'] ?? ''));
            $rows[] = [
                'label' => 'Fingerprint hash',
                'value' => $hash !== '' ? Str::limit($hash, 16, '...') : '-',
                'hint' => null,
            ];
            $rows[] = [
                'label' => 'Fingerprint recorded at',
                'value' => $this->formatSabreHostClassificationDisplayScalar($stored['recorded_at'] ?? null),
                'hint' => null,
            ];
        }

        if (($match['fingerprint_match'] ?? false) === true) {
            $rows[] = [
                'label' => 'Prior host rejection fingerprint match',
                'value' => 'Yes',
                'hint' => null,
            ];
            $rows[] = [
                'label' => 'Matched host error family',
                'value' => $this->formatSabreContinuityCode((string) ($match['matched_host_error_family'] ?? '')),
                'hint' => null,
            ];
            $rows[] = [
                'label' => 'Matched safe reason code',
                'value' => $this->formatSabreHostClassificationDisplayScalar($match['matched_safe_reason_code'] ?? null),
                'hint' => null,
            ];
            $rows[] = [
                'label' => 'Fingerprint retry policy',
                'value' => $this->formatSabreHostClassificationDisplayScalar($match['retry_policy'] ?? null),
                'hint' => null,
            ];
            $rows[] = [
                'label' => 'Fingerprint matched recorded at',
                'value' => $this->formatSabreHostClassificationDisplayScalar($match['matched_recorded_at'] ?? null),
                'hint' => null,
            ];
        }

        return $rows;
    }

    public function continuityDiagnosticPanel(Booking $booking): array
    {
        $title = 'Sabre continuity & host classification';
        $disclaimer = 'Read-only diagnostic from stored booking data. No live Sabre calls. Raw payloads, PII, and credentials are not shown.';
        $empty = [
            'show' => false,
            'unavailable' => false,
            'unavailable_message' => '',
            'title' => $title,
            'disclaimer' => $disclaimer,
            'summary_rows' => [],
            'source_present_rows' => [],
            'continuity_field_rows' => [],
        ];

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== SupplierProvider::Sabre->value) {
            return $empty;
        }

        try {
            $report = app(SabreBookingContinuityAuditor::class)->audit($booking);
        } catch (\Throwable $e) {
            Log::warning('sabre_continuity_diagnostic_unavailable', [
                'booking_id' => $booking->id,
                'exception' => $e::class,
                'message' => Str::limit($e->getMessage(), 120, ''),
            ]);

            return [
                'show' => true,
                'unavailable' => true,
                'unavailable_message' => 'Sabre continuity diagnostic unavailable',
                'title' => $title,
                'disclaimer' => $disclaimer,
                'summary_rows' => [],
                'source_present_rows' => [],
                'continuity_field_rows' => [],
            ];
        }

        if (($report['error'] ?? null) === 'booking_not_sabre') {
            return $empty;
        }

        if (($report['error'] ?? null) === 'no_offer_snapshot') {
            return [
                'show' => true,
                'unavailable' => true,
                'unavailable_message' => 'Sabre continuity diagnostic unavailable',
                'title' => $title,
                'disclaimer' => $disclaimer,
                'summary_rows' => [],
                'source_present_rows' => [],
                'continuity_field_rows' => [],
            ];
        }

        $overlay = is_array($report['host_outcome_overlay'] ?? null) ? $report['host_outcome_overlay'] : [];
        $checkout = is_array($meta['sabre_checkout_outcome'] ?? null) ? $meta['sabre_checkout_outcome'] : [];
        $classification = is_array($checkout['sabre_host_classification'] ?? null) ? $checkout['sabre_host_classification'] : [];
        $hostSafeReasonCode = $this->formatSabreHostClassificationDisplayScalar($classification['safe_reason_code'] ?? null);
        if ($hostSafeReasonCode !== '-') {
            $hostSafeReasonCode = str_replace('_', ' ', $hostSafeReasonCode);
        }

        $readinessRec = (string) ($report['readiness_recommendation'] ?? '');
        $finalRec = (string) ($report['final_diagnostic_recommendation'] ?? $readinessRec);
        $hostErrorFamily = $this->formatSabreContinuityCode((string) ($overlay['host_error_family'] ?? ''));

        $summaryRows = [
            [
                'label' => 'Readiness recommendation',
                'value' => $this->formatSabreContinuityCode($readinessRec),
                'hint' => $this->formatSabreContinuityRecommendationHint($readinessRec),
            ],
            [
                'label' => 'Final diagnostic recommendation',
                'value' => $this->formatSabreContinuityCode($finalRec),
                'hint' => $this->formatSabreContinuityRecommendationHint($finalRec),
            ],
            [
                'label' => 'Pricing context ready',
                'value' => $this->formatSabreYesNo($report['pricing_context_ready'] ?? null),
                'hint' => null,
            ],
            [
                'label' => 'Readiness reasons',
                'value' => $this->formatSabreContinuityReasons($report['readiness_reasons'] ?? []),
                'hint' => null,
            ],
            [
                'label' => 'Host outcome present',
                'value' => $this->formatSabreYesNo($overlay['host_outcome_present'] ?? null),
                'hint' => null,
            ],
            [
                'label' => 'Host outcome status',
                'value' => $this->formatSabreContinuityCode((string) ($overlay['host_outcome_status'] ?? '')),
                'hint' => null,
            ],
            [
                'label' => 'Host error family',
                'value' => $hostErrorFamily !== '-' ? $hostErrorFamily : '-',
                'hint' => $this->formatSabreContinuityHostErrorFamilyHint((string) ($overlay['host_error_family'] ?? '')),
            ],
            [
                'label' => 'Host checkout status',
                'value' => $this->formatSabreHostClassificationDisplayScalar($overlay['host_checkout_status'] ?? null),
                'hint' => null,
            ],
            [
                'label' => 'Host error code',
                'value' => $this->formatSabreHostClassificationDisplayScalar($overlay['host_error_code'] ?? null),
                'hint' => null,
            ],
            [
                'label' => 'Host safe reason code',
                'value' => $hostSafeReasonCode,
                'hint' => null,
            ],
            [
                'label' => 'Host rejection evidence present',
                'value' => $this->formatSabreYesNo($overlay['host_rejection_evidence_present'] ?? null),
                'hint' => null,
            ],
            ...$this->buildSabreHostRejectionFingerprintPanelRows($checkout, $meta),
            [
                'label' => 'Host rejected after local continuity',
                'value' => $this->formatSabreYesNo($overlay['host_rejected_after_local_continuity'] ?? null),
                'hint' => null,
            ],
            [
                'label' => 'Local continuity aligned',
                'value' => $this->formatSabreYesNo($overlay['local_continuity_aligned'] ?? null),
                'hint' => null,
            ],
        ];

        $sourceLabels = [
            'normalized_snapshot' => 'Normalized snapshot',
            'sabre_booking_context' => 'Sabre booking context',
            'sabre_shop_context' => 'Sabre shop context',
            'refreshed_offer_snapshot' => 'Refreshed offer snapshot',
            'revalidation_linkage' => 'Revalidation linkage',
            'pnr_draft' => 'PNR draft',
        ];
        $sourcesPresent = is_array($report['sources_present'] ?? null) ? $report['sources_present'] : [];
        $sourcePresentRows = [];
        foreach ($sourceLabels as $key => $label) {
            $sourcePresentRows[] = [
                'label' => $label,
                'value' => $this->formatSabreYesNo(($sourcesPresent[$key] ?? false) === true),
            ];
        }

        $continuityFieldRows = [];
        foreach ((array) ($report['continuity_rows'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $field = trim((string) ($row['field'] ?? ''));
            if ($field === '') {
                continue;
            }
            $continuityFieldRows[] = [
                'label' => str_replace('_', ' ', $field),
                'value' => $this->formatSabreContinuityFieldStatus((string) ($row['status'] ?? 'unknown')),
            ];
        }

        return [
            'show' => true,
            'unavailable' => false,
            'unavailable_message' => '',
            'title' => $title,
            'disclaimer' => $disclaimer,
            'summary_rows' => $summaryRows,
            'source_present_rows' => $sourcePresentRows,
            'continuity_field_rows' => $continuityFieldRows,
        ];
    }

    public function formatSabreContinuityCode(string $code): string
    {
        $slug = strtolower(trim($code));
        if ($slug === '' || $slug === 'none') {
            return '-';
        }

        return str_replace('_', ' ', $slug);
    }

    public function formatSabreContinuityRecommendationHint(string $code): ?string
    {
        return match (strtolower(trim($code))) {
            'auto_pnr_safe' => 'Local continuity is complete; not a guarantee of ticketing or final host pricing.',
            SabreBookingContinuityAuditor::FINAL_REC_BLOCKED_HOST_REJECTED => 'Local continuity was complete, but stored safe host classification indicates Sabre rejected sell/pricing.',
            'blocked_missing_rbd' => 'Booking class missing; do not auto-PNR.',
            'blocked_missing_fare_basis' => 'Fare basis missing; do not auto-PNR.',
            default => null,
        };
    }

    public function formatSabreContinuityHostErrorFamilyHint(string $family): ?string
    {
        $normalized = strtoupper(trim($family));
        if ($normalized === SabreBookingContinuityAuditor::HOST_ERROR_FAMILY_CERTIFIED_ROUTE_PENDING) {
            return 'Internal/manual/certification gate — not Sabre host rejection.';
        }

        return null;
    }

    /**
     * @param  list<mixed>|mixed  $reasons
     */
    public function formatSabreContinuityReasons(mixed $reasons): string
    {
        if (! is_array($reasons) || $reasons === []) {
            return '-';
        }
        $parts = [];
        foreach (array_slice($reasons, 0, 8) as $reason) {
            if (! is_scalar($reason)) {
                continue;
            }
            $text = trim((string) $reason);
            if ($text === '') {
                continue;
            }
            $parts[] = $this->readinessReasons->messageForCode($text);
        }

        return $parts !== [] ? implode('; ', $parts) : '-';
    }

    /**
     * F5: compact Sabre diagnostic summary (8 status groups, fail-soft).
     *
     * @param  array<string, mixed>|null  $supplierActions
     * @return array{show: bool, title: string, groups: list<array{key: string, label: string, status: string, detail: string, blockers: list<string>}>}
     */
    public function compactStatusPanel(Booking $booking, ?array $supplierActions = null): array
    {
        $empty = ['show' => false, 'title' => 'Sabre diagnostic summary', 'groups' => []];

        try {
            $sa = is_array($supplierActions) ? $supplierActions : app(AdminBookingSupplierActions::class)->build($booking, false, false);
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
            $isSabre = ($sa['is_sabre'] ?? false) === true || $provider === SupplierProvider::Sabre->value;

            if (! $isSabre) {
                return [
                    'show' => true,
                    'title' => 'Sabre diagnostic summary',
                    'groups' => [
                        $this->compactGroup('booking_identity', 'Booking identity', 'info', $booking->reference_code ?? ('#'.$booking->id), [
                            $this->readinessReasons->messageForCode('not_sabre_booking'),
                        ]),
                    ],
                ];
            }

            $connectionId = (int) ($meta['supplier_connection_id'] ?? 0);
            $pnr = trim((string) ($booking->pnr ?? ''));
            $supplierRef = trim((string) ($booking->supplier_reference ?? ''));
            $pnrDetail = $pnr !== '' ? $pnr : ($supplierRef !== '' ? $supplierRef : 'Not available');

            $revalidationStatus = trim((string) ($meta['sabre_revalidation_status'] ?? $meta['revalidation_status'] ?? ''));
            if ($revalidationStatus === '') {
                $revalidationDetail = 'Not evaluated yet';
            } else {
                $revalidationDetail = str_replace('_', ' ', $revalidationStatus);
            }

            $opsReadiness = is_array($sa['operational_pnr_readiness'] ?? null) ? $sa['operational_pnr_readiness'] : [];
            $pnrReadinessDetail = ($opsReadiness['summary_label'] ?? null) ?: (($sa['can_create_pnr'] ?? false) ? 'Eligible for PNR create' : (string) ($sa['create_pnr_reason'] ?? 'Not evaluated yet'));
            $pnrBlockers = [];
            if (($sa['can_create_pnr'] ?? false) !== true && ! empty($sa['create_pnr_reason'])) {
                $pnrBlockers[] = (string) $sa['create_pnr_reason'];
            }

            $syncStatus = ($sa['can_sync_pnr_itinerary'] ?? false) ? 'available' : 'blocked';
            $syncDetail = ($sa['can_sync_pnr_itinerary'] ?? false)
                ? 'Retrieve/sync available'
                : ((string) ($sa['sync_block_message'] ?? 'Not available'));
            $syncBlockers = [];
            if (! empty($sa['sync_block_message'])) {
                $syncBlockers[] = (string) $sa['sync_block_message'];
            }

            $ticketing = TicketingReadinessPresenter::forBooking($booking);
            $ticketingDetail = (string) ($ticketing['overall_label'] ?? 'Not evaluated yet');
            $ticketingBlockers = [];
            foreach ($ticketing['items'] ?? [] as $item) {
                if (($item['status'] ?? '') !== 'pass') {
                    $ticketingBlockers[] = (string) ($item['message'] ?? $item['label'] ?? '');
                }
            }

            $posture = is_array($sa['sabre_capability_posture'] ?? null) ? $sa['sabre_capability_posture'] : null;
            $cancelDetail = $posture !== null
                ? (string) ($posture['gds_cancel_label'] ?? 'Not evaluated yet')
                : 'Not evaluated yet';

            $controlled = is_array($sa['controlled_pnr_readiness'] ?? null) ? $sa['controlled_pnr_readiness'] : [];
            $controlledEligible = ($controlled['eligible'] ?? false) === true;
            $contextUsable = ($controlled['has_usable_controlled_pnr_context'] ?? false) === true;
            $contextReason = trim((string) ($controlled['controlled_context_reason_code'] ?? ''));
            $contextWarnings = is_array($controlled['controlled_context_warnings'] ?? null)
                ? array_values($controlled['controlled_context_warnings'])
                : [];
            $controlledDetail = $controlledEligible
                ? 'Eligible for controlled PNR readiness'
                : (string) ($controlled['human_message'] ?? 'Blocked for controlled PNR create');
            if ($contextUsable) {
                $controlledDetail = 'Controlled context usable — '.$controlledDetail;
            } elseif ($contextReason !== '') {
                $controlledDetail = 'Context '.$this->formatSabreContinuityCode($contextReason).' — '.$controlledDetail;
            }
            $controlledBlockers = [];
            if (! $controlledEligible && ! empty($controlled['human_message'])) {
                $controlledBlockers[] = (string) $controlled['human_message'];
            }
            if (! empty($controlled['recommended_next_action'])) {
                $controlledBlockers[] = (string) $controlled['recommended_next_action'];
            }
            if ($contextWarnings !== []) {
                $controlledBlockers[] = 'Warnings: '.$this->formatSabreContinuityReasons(array_slice($contextWarnings, 0, 3));
            }
            $controlledBlockers = array_values(array_unique(array_filter($controlledBlockers)));

            $groups = [
                $this->compactGroup('booking_identity', 'Booking identity', 'info', ($booking->reference_code ?? '#'.$booking->id).' · '.strtoupper($provider).' · '.($booking->status?->value ?? (string) $booking->status), []),
                $this->compactGroup('supplier_connection', 'Supplier connection', $connectionId > 0 ? 'ok' : 'missing', $connectionId > 0 ? 'Connection #'.$connectionId : 'Not available', $connectionId <= 0 ? [$this->readinessReasons->messageForCode('missing_supplier_connection')] : []),
                $this->compactGroup('stored_pnr', 'Stored PNR / supplier reference', $pnrDetail !== 'Not available' ? 'ok' : 'missing', $pnrDetail, $pnrDetail === 'Not available' ? [$this->readinessReasons->messageForCode('missing_sabre_pnr')] : []),
            ];

            $gdsLifecycle = app(SabreGdsAutoPnrLifecycleService::class)->resolveForAdmin($booking);
            if (($gdsLifecycle['applies'] ?? false) === true) {
                $lifecycleDetail = collect($gdsLifecycle['rows'] ?? [])
                    ->map(static fn (array $row): string => $row['label'].': '.($row['reached'] ? 'Yes' : 'No'))
                    ->implode(' · ');
                $lifecycleBlockers = [];
                if (($gdsLifecycle['airline_segment_status'] ?? null) !== null) {
                    $lifecycleBlockers[] = 'Segment status: '.$gdsLifecycle['airline_segment_status'];
                }
                if (($gdsLifecycle['airline_locator'] ?? null) !== null) {
                    $lifecycleBlockers[] = 'Airline locator: '.$gdsLifecycle['airline_locator'];
                }
                if (($gdsLifecycle['supplier_pnr_expires_at'] ?? null) !== null) {
                    $lifecycleBlockers[] = 'PNR expiry: '.$gdsLifecycle['supplier_pnr_expires_at'];
                }
                $groups[] = $this->compactGroup(
                    'gds_auto_pnr_lifecycle',
                    'GDS auto-PNR lifecycle',
                    ($gdsLifecycle['pnr_created'] ?? false) ? 'ok' : 'info',
                    $lifecycleDetail !== '' ? $lifecycleDetail : 'Not started',
                    $lifecycleBlockers,
                );
            }

            $groups = array_merge($groups, [
                $this->compactGroup('revalidation', 'Revalidation status', $revalidationDetail === 'Not evaluated yet' ? 'unknown' : 'info', $revalidationDetail, []),
                $this->compactGroup('controlled_pnr_readiness', 'Controlled PNR readiness', $controlledEligible ? 'ok' : 'blocked', $controlledDetail, $controlledBlockers),
                $this->compactGroup('pnr_readiness', 'PNR readiness', ($sa['can_create_pnr'] ?? false) ? 'ok' : 'blocked', $pnrReadinessDetail, $pnrBlockers),
                $this->compactGroup('retrieve_sync', 'Retrieve/sync status', $syncStatus, $syncDetail, $syncBlockers),
                $this->compactGroup('ticketing', 'Ticketing status', ($ticketing['overall_status'] ?? '') === self::TICKETING_READY ? 'ok' : 'blocked', $ticketingDetail, array_slice(array_filter($ticketingBlockers), 0, 4)),
                $this->compactGroup('cancellation', 'Cancellation status', 'info', $cancelDetail, []),
            ]);

            return [
                'show' => true,
                'title' => 'Sabre diagnostic summary',
                'groups' => $groups,
            ];
        } catch (\Throwable $e) {
            Log::warning('sabre_compact_diagnostic_panel_unavailable', [
                'booking_id' => $booking->id,
                'exception' => $e::class,
                'message' => Str::limit($e->getMessage(), 120, ''),
            ]);

            return $empty;
        }
    }

    private const TICKETING_READY = 'ready_except_ticketing_disabled';

    /**
     * @param  list<string>  $blockers
     * @return array{key: string, label: string, status: string, detail: string, blockers: list<string>}
     */
    private function compactGroup(string $key, string $label, string $status, string $detail, array $blockers): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'detail' => $detail !== '' ? $detail : 'Not available',
            'blockers' => array_values(array_filter($blockers)),
        ];
    }

    public function formatSabreContinuityFieldStatus(string $status): string
    {
        $slug = strtolower(trim($status));

        return $slug !== '' ? $slug : 'unknown';
    }

    /**
     * @param  array<string, mixed>  $classification
     * @return array{fields: array<string, string>, signal_badges: list<string>}
     */
    public function adminSafeSabreHostClassificationFields(array $classification): array
    {
        $safeReasonCode = $this->formatSabreHostClassificationDisplayScalar($classification['safe_reason_code'] ?? null);
        $safeSummary = $this->formatSabreHostClassificationDisplayScalar($classification['safe_summary'] ?? null);
        $recommendedAction = $this->formatSabreHostClassificationDisplayScalar($classification['recommended_admin_action'] ?? null);

        if ($safeReasonCode === '-' && $safeSummary === '-' && $recommendedAction === '-') {
            return ['fields' => [], 'signal_badges' => []];
        }

        $retryPolicySlug = trim((string) ($classification['retry_policy'] ?? ''));
        $signalBadges = [];
        foreach ((array) ($classification['matched_signals'] ?? []) as $signal) {
            if (! is_scalar($signal)) {
                continue;
            }
            $sanitized = $this->formatSabreHostClassificationDisplayScalar((string) $signal);
            if ($sanitized === '-' || $sanitized === '[redacted]') {
                continue;
            }
            $signalBadges[] = $sanitized;
        }
        $signalBadges = array_values(array_unique(array_slice($signalBadges, 0, 8)));

        $fields = [
            'safe_reason_code' => $safeReasonCode !== '-'
                ? str_replace('_', ' ', $safeReasonCode)
                : '-',
            'safe_summary' => $safeSummary,
            'recommended_admin_action' => $recommendedAction,
            'retry_policy' => $retryPolicySlug !== '' ? $retryPolicySlug : '-',
            'retry_policy_label' => $this->formatSabreHostRetryPolicyAdvisory($retryPolicySlug !== '' ? $retryPolicySlug : null),
            'manual_review_required' => $this->formatSabreYesNo($classification['manual_review_required'] ?? null),
            'source_layer' => $this->formatSabreHostClassificationDisplayScalar($classification['source_layer'] ?? null) !== '-'
                ? str_replace('_', ' ', $this->formatSabreHostClassificationDisplayScalar($classification['source_layer'] ?? null))
                : '-',
            'matched_signals' => $signalBadges !== [] ? implode(', ', $signalBadges) : '-',
        ];

        return ['fields' => $fields, 'signal_badges' => $signalBadges];
    }

    public function formatSabreHostRetryPolicyAdvisory(?string $retryPolicy): string
    {
        $slug = strtolower(trim((string) $retryPolicy));
        if ($slug === '') {
            return '-';
        }

        return match ($slug) {
            'no_retry_same_offer' => 'Do not retry the same offer — re-shop for a fresh itinerary.',
            'no_retry_until_credentials_or_pcc_checked' => 'Do not retry until PCC/credentials are verified.',
            'retry_only_after_operator_review_or_idempotency_check' => 'Retry only after operator review or an idempotency check.',
            'no_auto_retry' => 'No automatic retry — manual review required.',
            default => $slug.' (advisory code)',
        };
    }

    protected function formatSabreHostClassificationDisplayScalar(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '-';
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '-';
        }

        $lower = strtolower($text);
        foreach ([
            'createpassengernamerecordrq',
            'passengername',
            'formofpayment',
            'telephone',
            'targetcity',
            'token',
            'credentials',
        ] as $forbidden) {
            if (str_contains($lower, $forbidden)) {
                return '[redacted]';
            }
        }

        if (preg_match('/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/', $text) === 1) {
            return '[redacted]';
        }

        if (preg_match('/@[a-z0-9._-]+\.[a-z]{2,}/i', $text) === 1) {
            return '[redacted]';
        }

        return $text;
    }

    /**
     * Local inspect commands: same safe field names as admin booking panel (scalar lines only).
     *
     * @param  array<string, mixed>  $merged
     * @return array<string, string>
     */
    public function adminSafeSabreDiagnosticFieldsForOutput(array $merged): array
    {
        $pick = fn (string $key, mixed $default = null): mixed => $this->pickSabreDiagnosticValue($merged, $key, $default);

        $certified = $pick('certified_route_result');
        $certifiedLabel = 'Not recorded yet';
        if (is_array($certified)) {
            $status = trim((string) ($certified['route_status'] ?? $certified['status'] ?? ''));
            $certifiedLabel = $status !== '' ? $status : 'Not recorded yet';
        } elseif (is_scalar($certified) && trim((string) $certified) !== '') {
            $certifiedLabel = (string) $certified;
        }

        $blocksPresent = $pick('cpnr_required_blocks_present');
        $blocksMissing = $pick('cpnr_required_blocks_missing');

        return array_filter([
            'provider' => $this->formatSabreDiagnosticScalar($pick('provider', SupplierProvider::Sabre->value)),
            'supplier_connection_id' => $this->formatSabreDiagnosticScalar($pick('supplier_connection_id')),
            'pcc_present' => $this->formatSabreYesNo($pick('pcc_present')),
            'pcc_fingerprint' => $this->formatPccFingerprint($pick('pcc_last2'), $pick('pcc_hash')),
            'target_city_present' => $this->formatSabreYesNo($pick('target_city_present')),
            'selected_payload_style' => $this->formatSabreDiagnosticScalar($pick('selected_payload_style', $pick('payload_style'))),
            'selected_endpoint_path' => $this->formatSabreDiagnosticScalar($pick('selected_endpoint_path', $pick('endpoint_path'))),
            'endpoint_version' => $this->formatSabreDiagnosticScalar($pick('selected_endpoint_version', $pick('endpoint_version'))),
            'iati_like_selected' => $this->formatSabreYesNo($pick('iati_like_selected')),
            'iati_like_eligible' => $this->formatSabreYesNo($pick('iati_like_eligible')),
            'iati_like_reason_code' => $this->formatSabreDiagnosticScalar($pick('iati_like_reason_code')),
            'certified_route_result' => $certifiedLabel,
            'freshness_strategy' => $this->formatSabreDiagnosticScalar($pick('freshness_strategy', $pick('strategy'))),
            'revalidation_required' => $this->formatSabreYesNo($pick('revalidation_required')),
            'revalidation_skipped' => $this->formatSabreYesNo($pick('revalidation_skipped', $pick('revalidation_skipped_by_config'))),
            'revalidation_skip_reason' => $this->formatSabreDiagnosticScalar($pick(
                'revalidation_skip_reason',
                $pick('prebooking_revalidation_skipped_reason', $pick('skip_reason'))
            )),
            'refresh_required' => $this->formatSabreYesNo($pick('refresh_required')),
            'refresh_attempted' => $this->formatSabreYesNo($pick('refresh_attempted')),
            'refresh_status' => $this->formatSabreDiagnosticScalar($pick('refresh_status', $pick('refresh_result'))),
            'segment_count' => $this->formatSabreDiagnosticScalar($pick('segment_count')),
            'rbd_coverage' => $this->formatRbdCoverage($merged),
            'fare_basis_coverage' => $this->formatFareBasisCoverage($merged),
            'cpnr_required_blocks_present' => $this->formatBlockList($blocksPresent),
            'cpnr_required_blocks_missing' => $this->formatBlockList($blocksMissing),
            'pnr_attempted' => $this->formatSabreYesNo($pick('pnr_attempted', $pick('live_call_attempted'))),
            'pnr_created' => $this->formatSabreYesNo($pick('pnr_created', $pick('pnr_present'))),
            'pnr_locator' => $this->formatSabreDiagnosticScalar($pick('pnr', $pick('pnr_locator'))),
            'manual_review_required' => $this->formatSabreYesNo($pick('manual_review_required', $pick('manual_review'))),
            'manual_review_reason_code' => $this->formatSabreDiagnosticScalar($pick(
                'manual_review_reason_code',
                $pick('safe_reason_code', $pick('reason_code', $pick('error_code')))
            )),
            'safe_sabre_error_summary' => $this->formatSabreDiagnosticScalar($pick(
                'safe_error_message_summary',
                $pick('sabre_error_code', $pick('safe_message'))
            )),
        ]);
    }

    /**
     * Sprint 11A: Multi-segment readiness scalars for admin panel (no raw payloads / PII).
     *
     * @param  array<string, mixed>  $diag
     * @return array<string, string>
     */
    /**
     * Sprint 11J: UC / HaltOnStatus host sell reject scalars (from attempt safe_summary only).
     *
     * @param  array<string, mixed>  $merged
     * @return array<string, string>
     */
    public function adminSafeHostSellRejectFields(array $merged): array
    {
        if (! SabrePnrFailureClassifier::safeSummaryIndicatesHostSellRejectedUc($merged)) {
            return [];
        }

        $flights = [];
        foreach ((array) ($merged['affected_flight_numbers'] ?? []) as $flight) {
            if (is_scalar($flight) && trim((string) $flight) !== '') {
                $flights[] = strtoupper(trim((string) $flight));
            }
        }
        $flights = array_values(array_unique(array_slice($flights, 0, 8)));
        $blockers = is_array($merged['retry_blocker_reasons'] ?? null) ? $merged['retry_blocker_reasons'] : [];

        return array_filter([
            'airline_segment_status' => $this->formatSabreDiagnosticScalar($merged['airline_segment_status'] ?? 'UC'),
            'affected_flight_numbers' => $flights !== [] ? implode(', ', $flights) : '-',
            'halt_on_status_received' => $this->formatSabreYesNo($merged['halt_on_status_received'] ?? true),
            'retry_blocker_reasons' => $blockers !== [] ? implode(', ', array_map('strval', array_slice($blockers, 0, 8))) : '-',
            'host_sell_reject_suggested_action' => 'Fresh search / alternate itinerary (do not retry same offer)',
        ]);
    }

    /**
     * BF7-J-OPS-FIX2: NN halt / operational allow-NN HaltOnStatus policy (attempt safe_summary only).
     *
     * @param  array<string, mixed>  $merged
     * @return array<string, string>
     */
    public function adminSafeHostSellPendingNnFields(array $merged): array
    {
        if (! SabrePnrFailureClassifier::safeSummaryIndicatesHostSellPendingNn($merged)) {
            return [];
        }

        $flights = [];
        foreach ((array) ($merged['affected_flight_numbers'] ?? []) as $flight) {
            if (is_scalar($flight) && trim((string) $flight) !== '') {
                $flights[] = strtoupper(trim((string) $flight));
            }
        }
        $flights = array_values(array_unique(array_slice($flights, 0, 8)));

        return array_filter([
            'airline_segment_status' => $this->formatSabreDiagnosticScalar($merged['airline_segment_status'] ?? 'NN'),
            'affected_flight_numbers' => $flights !== [] ? implode(', ', $flights) : '-',
            'halt_on_status_received' => $this->formatSabreYesNo($merged['halt_on_status_received'] ?? true),
            'nn_halt_fatal_without_policy' => $this->formatSabreYesNo($merged['create_nn_halt_fatal_without_policy'] ?? true),
            'host_sell_pending_nn_suggested_action' => SabreCpnrOperationalAllowNnPolicy::isConfigEnabled()
                ? 'Retry with operational allow-NN (omit NN/WN from HaltOnStatus) or choose HK-capable itinerary'
                : 'Enable operational allow-NN flag or choose another itinerary',
        ]);
    }

    /**
     * @param  array<string, mixed>  $merged
     * @return array<string, string>
     */
    public function adminSafeCreatePayloadHaltPolicyFields(array $merged): array
    {
        $hasPolicy = array_key_exists('create_halt_on_status_policy', $merged)
            || array_key_exists('allow_nn_cert_operational', $merged)
            || array_key_exists('create_halt_on_status_codes', $merged);

        if (! $hasPolicy) {
            return [];
        }

        $haltCodes = is_array($merged['create_halt_on_status_codes'] ?? null)
            ? implode(', ', array_map('strval', array_slice($merged['create_halt_on_status_codes'], 0, 12)))
            : $this->formatSabreDiagnosticScalar($merged['create_halt_on_status_codes'] ?? null);

        return array_filter([
            'create_segment_sell_status_intent' => $this->formatSabreDiagnosticScalar($merged['create_segment_sell_status_intent'] ?? 'NN'),
            'create_halt_on_status_codes' => $haltCodes !== '' ? $haltCodes : '-',
            'create_halt_on_status_nn_omitted' => $this->formatSabreYesNo($merged['create_halt_on_status_nn_omitted'] ?? $merged['halt_on_status_nn_omitted'] ?? false),
            'create_halt_on_status_policy' => $this->formatSabreDiagnosticScalar($merged['create_halt_on_status_policy'] ?? $merged['halt_on_status_policy'] ?? null),
            'allow_nn_cert_operational' => $this->formatSabreYesNo($merged['allow_nn_cert_operational'] ?? false),
            'pnr_confirmed_requires_hk_or_ss' => 'Yes when allow-NN operational active',
        ]);
    }

    public function adminSafeMultiSegmentReadinessFields(array $diag): array
    {
        if (($diag['multi_segment_candidate'] ?? false) !== true && (int) ($diag['segment_count'] ?? 0) < 2) {
            return array_filter([
                'multi_segment_candidate' => $this->formatSabreYesNo($diag['multi_segment_candidate'] ?? false),
                'segment_count' => $this->formatSabreDiagnosticScalar($diag['segment_count'] ?? 0),
            ]);
        }

        $blockers = is_array($diag['multi_segment_blocker_reasons'] ?? null) ? $diag['multi_segment_blocker_reasons'] : [];

        return array_filter([
            'multi_segment_candidate' => $this->formatSabreYesNo($diag['multi_segment_candidate'] ?? false),
            'connecting_same_carrier_candidate' => $this->formatSabreYesNo($diag['connecting_same_carrier_candidate'] ?? false),
            'connecting_same_carrier_enabled' => $this->formatSabreYesNo($diag['connecting_same_carrier_enabled'] ?? false),
            'connecting_same_carrier_public_checkout_enabled' => $this->formatSabreYesNo($diag['connecting_same_carrier_public_checkout_enabled'] ?? false),
            'segment_count' => $this->formatSabreDiagnosticScalar($diag['segment_count'] ?? null),
            'carrier_chain' => $this->formatSabreDiagnosticScalar($diag['carrier_chain'] ?? null),
            'validating_carrier' => $this->formatSabreDiagnosticScalar($diag['validating_carrier'] ?? null),
            'mixed_carrier' => $this->formatSabreYesNo($diag['mixed_carrier'] ?? null),
            'mixed_carrier_candidate' => $this->formatSabreYesNo($diag['mixed_carrier_candidate'] ?? false),
            'marketing_carriers_by_segment' => $this->formatSabreDiagnosticScalar($diag['marketing_carriers_by_segment'] ?? null),
            'operating_carriers_by_segment' => $this->formatSabreDiagnosticScalar($diag['operating_carriers_by_segment'] ?? null),
            'interline_candidate' => $this->formatSabreYesNo($diag['interline_candidate'] ?? false),
            'validating_carrier_present' => $this->formatSabreYesNo($diag['validating_carrier_present'] ?? false),
            'proposed_mixed_carrier_category' => $this->formatSabreDiagnosticScalar($diag['proposed_mixed_carrier_category'] ?? null),
            'mixed_carrier_public_checkout_enabled' => $this->formatSabreYesNo($diag['mixed_carrier_public_checkout_enabled'] ?? false),
            'mixed_carrier_admin_enabled' => $this->formatSabreYesNo($diag['mixed_carrier_admin_enabled'] ?? false),
            'mixed_carrier_next_step' => $this->formatSabreDiagnosticScalar($diag['mixed_carrier_next_step'] ?? null),
            'mixed_carrier_readiness_blockers' => is_array($diag['mixed_carrier_readiness_blockers'] ?? null) && $diag['mixed_carrier_readiness_blockers'] !== []
                ? implode(', ', array_map('strval', array_slice($diag['mixed_carrier_readiness_blockers'], 0, 12)))
                : '-',
            'codeshare_present' => $this->formatSabreYesNo($diag['codeshare_present'] ?? false),
            'operating_carrier_missing_count' => $this->formatSabreDiagnosticScalar($diag['operating_carrier_missing_count'] ?? null),
            'rbd_complete' => $this->formatSabreYesNo($diag['rbd_complete'] ?? null),
            'fare_basis_complete' => $this->formatSabreYesNo($diag['fare_basis_complete'] ?? null),
            'segment_context_complete' => $this->formatSabreYesNo($diag['segment_context_complete'] ?? null),
            'iati_like_multi_segment_ready' => $this->formatSabreYesNo($diag['iati_like_multi_segment_ready'] ?? null),
            'iati_like_connecting_ready' => $this->formatSabreYesNo($diag['iati_like_connecting_ready'] ?? null),
            'proposed_certification_category' => $this->formatSabreDiagnosticScalar($diag['proposed_certification_category'] ?? null),
            'certified_route_category' => $this->formatSabreDiagnosticScalar($diag['certified_route_category'] ?? null),
            'trip_type_detected' => $this->formatSabreDiagnosticScalar($diag['trip_type_detected'] ?? null),
            'passenger_records_multi_segment_enabled' => $this->formatSabreYesNo($diag['passenger_records_multi_segment_enabled'] ?? null),
            'passenger_records_multi_segment_eligible' => $this->formatSabreYesNo($diag['passenger_records_multi_segment_eligible'] ?? null),
            'admin_staff_pnr_retry_route_allowed' => $this->formatSabreYesNo($diag['admin_staff_pnr_retry_route_allowed'] ?? false),
            'admin_staff_pnr_readiness_passed' => $this->formatSabreYesNo($diag['admin_staff_pnr_readiness_passed'] ?? $diag['admin_staff_pnr_retry_allowed'] ?? false),
            'admin_staff_pnr_retry_allowed' => $this->formatSabreYesNo($diag['admin_staff_pnr_readiness_passed'] ?? $diag['admin_staff_pnr_retry_allowed'] ?? false),
            'admin_pnr_live_action_allowed' => $this->formatSabreYesNo($diag['admin_pnr_live_action_allowed'] ?? false),
            'context_refresh_available' => $this->formatSabreYesNo($diag['context_refresh_available'] ?? false),
            'rbd_source' => $this->formatSabreDiagnosticScalar($diag['rbd_source'] ?? null),
            'fare_basis_source' => $this->formatSabreDiagnosticScalar($diag['fare_basis_source'] ?? null),
            'pricing_context_ready' => $this->formatSabreYesNo($diag['pricing_context_ready'] ?? null),
            'pricing_context_missing_fields' => is_array($diag['pricing_context_missing_fields'] ?? null) && $diag['pricing_context_missing_fields'] !== []
                ? implode(', ', array_map('strval', array_slice($diag['pricing_context_missing_fields'], 0, 8)))
                : '-',
            'pricing_context_policy' => $this->formatSabreDiagnosticScalar($diag['pricing_context_policy'] ?? $diag['pricing_context_policy_used'] ?? null),
            'bfm_itinerary_reference_present' => $this->formatSabreYesNo($diag['bfm_itinerary_reference_present'] ?? null),
            'bfm_pricing_information_index_present' => $this->formatSabreYesNo($diag['bfm_pricing_information_index_present'] ?? null),
            'bfm_pricing_information_index' => $this->formatSabreDiagnosticScalar($diag['bfm_pricing_information_index'] ?? null),
            'formal_offer_reference_required' => $this->formatSabreDiagnosticScalar($diag['formal_offer_reference_required'] ?? null),
            'formal_pricing_information_ref_required' => $this->formatSabreDiagnosticScalar($diag['formal_pricing_information_ref_required'] ?? null),
            'shop_identifiers_present' => $this->formatSabreYesNo($diag['shop_identifiers_present'] ?? null),
            'pricing_information_ref_present' => $this->formatSabreYesNo($diag['pricing_information_ref_present'] ?? null),
            'offer_reference_present' => $this->formatSabreYesNo($diag['offer_reference_present'] ?? null),
            'itinerary_reference_present' => $this->formatSabreYesNo($diag['itinerary_reference_present'] ?? null),
            'pricing_linkage_source' => $this->formatSabreDiagnosticScalar($diag['pricing_linkage_source'] ?? null),
            'context_can_be_rebuilt' => $this->formatSabreYesNo($diag['context_can_be_rebuilt'] ?? null),
            'controlled_certification_required' => $this->formatSabreYesNo($diag['controlled_certification_required'] ?? false),
            'multi_segment_blocker_reasons' => $blockers !== [] ? implode(', ', array_map('strval', array_slice($blockers, 0, 12))) : '-',
            'blocker_reasons' => $blockers !== [] ? implode(', ', array_map('strval', array_slice($blockers, 0, 12))) : '-',
        ]);
    }

    /**
     * @return array{
     *     is_sabre: bool,
     *     merged: array<string, mixed>,
     *     last_attempt_at: ?string,
     *     create_attempt_label: ?string,
     *     sync_attempt_label: ?string,
     *     ticketing_attempt_label: ?string
     * }
     */
    protected function collectSabreDiagnosticLayers(Booking $booking): array
    {
        $booking->loadMissing(['supplierBookingAttempts']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        $checkout = is_array($meta['sabre_checkout_outcome'] ?? null) ? $meta['sabre_checkout_outcome'] : [];
        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $latestAttempt = $booking->supplierBookingAttempts->sortByDesc('created_at')->first();
        $createAttempt = $this->latestSabreSupplierAttemptByActions($booking, [
            'create_pnr',
            SabrePnrCertificationSupport::ACTION_CERTIFICATION,
        ]);
        $syncAttempt = $this->latestSabreSupplierAttemptByActions($booking, ['pnr_retrieve']);
        $ticketingAttempt = $this->latestSabreSupplierAttemptByActions($booking, ['issue_ticket']);
        $diagnosticAttempt = $createAttempt ?? $latestAttempt;
        $context = app(SupplierLifecycleContextResolver::class)->resolve($booking);
        $isSabre = $context['handler_key'] === SupplierLifecycleContextResolver::HANDLER_SABRE_GDS;

        $safe = is_array($diagnosticAttempt?->safe_summary) ? $diagnosticAttempt->safe_summary : [];
        $contextSummary = is_array($safe['booking_context_summary'] ?? null) ? $safe['booking_context_summary'] : [];
        $freshness = is_array($safe['freshness_strategy_decision'] ?? null) ? $safe['freshness_strategy_decision'] : [];
        if ($freshness === [] && is_array($safe['freshness_strategy_decision_json'] ?? null)) {
            $freshness = $safe['freshness_strategy_decision_json'];
        }

        $flatSafe = $safe;
        unset(
            $flatSafe['booking_context_summary'],
            $flatSafe['freshness_strategy_decision'],
            $flatSafe['freshness_strategy_decision_json'],
            $flatSafe['passenger_records_style_decision'],
        );

        $attemptStatus = strtolower(trim((string) ($diagnosticAttempt?->status ?? '')));
        $checkoutStatus = strtolower(trim((string) ($checkout['status'] ?? '')));

        $merged = array_merge(
            $handoff,
            $checkout,
            $contextSummary,
            $freshness,
            $this->flattenSabrePassengerRecordsStyleDecision($safe),
            $flatSafe,
            array_filter([
                'provider' => $provider !== '' ? $provider : SupplierProvider::Sabre->value,
                'supplier_connection_id' => $meta['supplier_connection_id'] ?? $diagnosticAttempt?->supplier_connection_id,
                'pnr' => trim((string) ($booking->pnr ?? '')) !== '' ? $booking->pnr : ($safe['pnr'] ?? null),
                'pnr_created' => trim((string) ($booking->pnr ?? '')) !== ''
                    || in_array((string) ($booking->supplier_booking_status ?? ''), ['created', 'pending_ticketing', 'ticketed'], true)
                    || trim((string) ($createAttempt?->supplier_reference ?? '')) !== '',
                'manual_review_required' => in_array($attemptStatus, ['needs_review', 'manual_review'], true)
                    || $checkoutStatus === 'needs_review'
                    || ($checkout['manual_review_required'] ?? false) === true,
                'manual_review_reason_code' => $diagnosticAttempt?->error_code ?? ($checkout['error_code'] ?? null),
            ], static fn ($v): bool => is_bool($v) || ($v !== null && $v !== '')),
        );

        $lastAttemptAt = $latestAttempt?->completed_at?->format('Y-m-d H:i')
            ?? $latestAttempt?->attempted_at?->format('Y-m-d H:i');

        return [
            'is_sabre' => $isSabre,
            'merged' => $merged,
            'last_attempt_at' => $lastAttemptAt,
            'create_attempt_label' => $this->formatSabreSupplierAttemptLabel($createAttempt),
            'sync_attempt_label' => $this->formatSabreSupplierAttemptLabel($syncAttempt),
            'ticketing_attempt_label' => $this->formatSabreSupplierAttemptLabel($ticketingAttempt),
        ];
    }

    /**
     * @param  list<string>  $actions
     */
    protected function latestSabreSupplierAttemptByActions(Booking $booking, array $actions): ?SupplierBookingAttempt
    {
        $normalized = array_map(static fn (string $action): string => strtolower(trim($action)), $actions);

        return $booking->supplierBookingAttempts
            ->filter(static function (SupplierBookingAttempt $attempt) use ($normalized): bool {
                return in_array(strtolower(trim((string) $attempt->action)), $normalized, true);
            })
            ->sortByDesc(static fn (SupplierBookingAttempt $attempt): mixed => $attempt->completed_at ?? $attempt->attempted_at ?? $attempt->created_at)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     * @return array<string, mixed>
     */
    protected function flattenSabrePassengerRecordsStyleDecision(array $safeSummary): array
    {
        $style = $safeSummary['passenger_records_style_decision'] ?? null;
        if (! is_array($style) || $style === []) {
            return [];
        }

        $flat = $style;
        unset($flat['reasons']);
        if (! isset($flat['payload_style']) && isset($style['selected_payload_style'])) {
            $flat['payload_style'] = $style['selected_payload_style'];
        }

        return array_filter($flat, static fn ($v): bool => is_bool($v) || ($v !== null && $v !== ''));
    }

    protected function formatSabreSupplierAttemptLabel(?SupplierBookingAttempt $attempt): ?string
    {
        if ($attempt === null) {
            return null;
        }

        $action = trim((string) ($attempt->action ?? ''));
        $status = trim((string) ($attempt->status ?? ''));
        $at = $attempt->completed_at?->format('Y-m-d H:i')
            ?? $attempt->attempted_at?->format('Y-m-d H:i');
        $parts = array_filter([$action !== '' ? $action : null, $status !== '' ? $status : null, $at]);

        return $parts === [] ? null : implode(display_sep_dot(), $parts);
    }

    /**
     * @param  array<string, mixed>  $merged
     */
    protected function pickSabreDiagnosticValue(array $merged, string $key, mixed $default = null): mixed
    {
        if ($this->isBlockedSabreDiagnosticKey($key)) {
            return $default;
        }
        if (array_key_exists($key, $merged)) {
            $v = $merged[$key];
            if (is_bool($v)) {
                return $v;
            }
            if ($v !== null && $v !== '') {
                return $v;
            }
        }

        return $default;
    }

    protected function isBlockedSabreDiagnosticKey(string $key): bool
    {
        $blocked = [
            'request_payload', 'response_payload', 'redacted_wire_request_body', 'password', 'token',
            'api_key', 'secret', 'passport', 'document_number', 'date_of_birth', 'birth_date',
        ];
        $lower = strtolower($key);
        foreach ($blocked as $fragment) {
            if (str_contains($lower, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $fields
     * @return list<array{label: string, value: string, badge: ?string}>
     */
    protected function sabrePnrReadinessRowsFromFields(
        array $fields,
        Booking $booking,
        ?string $lastAttemptAt,
        ?string $createAttemptLabel = null,
        ?string $syncAttemptLabel = null,
        ?string $ticketingAttemptLabel = null,
    ): array {
        $labels = [
            'provider' => 'Provider',
            'supplier_connection_id' => 'Supplier connection ID',
            'pcc_present' => 'PCC present',
            'pcc_fingerprint' => 'PCC fingerprint',
            'target_city_present' => 'Target city present',
            'selected_payload_style' => 'Selected payload style',
            'selected_endpoint_path' => 'Selected endpoint path',
            'endpoint_version' => 'Endpoint version',
            'iati_like_selected' => 'IATI-like selected',
            'iati_like_eligible' => 'IATI-like eligible',
            'iati_like_reason_code' => 'IATI-like reason code',
            'certified_route_result' => 'Certified route result',
            'freshness_strategy' => 'Freshness strategy',
            'revalidation_required' => 'Revalidation required',
            'revalidation_skipped' => 'Revalidation skipped',
            'revalidation_skip_reason' => 'Revalidation skip reason',
            'refresh_required' => 'Refresh required',
            'refresh_attempted' => 'Refresh attempted',
            'refresh_status' => 'Refresh status',
            'segment_count' => 'Segment count',
            'rbd_coverage' => 'RBD coverage',
            'fare_basis_coverage' => 'Fare basis coverage',
            'cpnr_required_blocks_present' => 'Required CPNR blocks present',
            'cpnr_required_blocks_missing' => 'Required CPNR blocks missing',
            'pnr_attempted' => 'PNR attempted',
            'pnr_created' => 'PNR created',
            'pnr_locator' => 'PNR / locator',
            'manual_review_required' => 'Manual review required',
            'manual_review_reason_code' => 'Manual review reason code',
            'safe_sabre_error_summary' => 'Safe Sabre error summary',
            'multi_segment_candidate' => 'Multi-segment candidate',
            'carrier_chain' => 'Carrier chain',
            'validating_carrier' => 'Validating carrier',
            'mixed_carrier' => 'Mixed carrier',
            'mixed_carrier_candidate' => 'Mixed-carrier 2-segment candidate',
            'marketing_carriers_by_segment' => 'Marketing carriers (by segment)',
            'operating_carriers_by_segment' => 'Operating carriers (by segment)',
            'interline_candidate' => 'Interline candidate (no codeshare)',
            'validating_carrier_present' => 'Validating carrier present',
            'proposed_mixed_carrier_category' => 'Proposed mixed-carrier category',
            'mixed_carrier_public_checkout_enabled' => 'Mixed-carrier public checkout',
            'mixed_carrier_admin_enabled' => 'Mixed-carrier admin PNR',
            'mixed_carrier_next_step' => 'Mixed-carrier next step',
            'mixed_carrier_readiness_blockers' => 'Mixed-carrier readiness blockers',
            'operating_carrier_missing_count' => 'Operating carrier missing (count)',
            'rbd_complete' => 'RBD complete (all segments)',
            'fare_basis_complete' => 'Fare basis complete (all segments)',
            'segment_context_complete' => 'Segment sell context complete',
            'iati_like_multi_segment_ready' => 'IATI-like multi-segment wire ready',
            'proposed_certification_category' => 'Proposed certification category',
            'certified_route_category' => 'Certified route category (current)',
            'trip_type_detected' => 'Trip type detected',
            'passenger_records_multi_segment_enabled' => 'B65 multi-segment config enabled',
            'passenger_records_multi_segment_eligible' => 'B65 multi-segment eligible',
            'multi_segment_blocker_reasons' => 'Multi-segment blocker reasons',
            'connecting_same_carrier_candidate' => 'Same-carrier 2-segment candidate',
            'connecting_same_carrier_enabled' => 'Controlled certification enabled',
            'connecting_same_carrier_public_checkout_enabled' => 'Public checkout live PNR',
            'codeshare_present' => 'Codeshare present',
            'iati_like_connecting_ready' => 'IATI-like connecting wire ready',
            'admin_staff_pnr_retry_route_allowed' => 'Admin/staff PNR route allowed',
            'admin_staff_pnr_readiness_passed' => 'Admin/staff PNR readiness passed',
            'admin_staff_pnr_retry_allowed' => 'Admin/staff PNR readiness passed',
            'admin_pnr_live_action_allowed' => 'Admin/staff PNR live action allowed',
            'context_refresh_available' => 'Context refresh available',
            'rbd_source' => 'RBD source',
            'fare_basis_source' => 'Fare basis source',
            'pricing_context_ready' => 'Pricing context ready',
            'pricing_context_missing_fields' => 'Pricing context missing fields',
            'pricing_context_policy' => 'Pricing context policy',
            'bfm_itinerary_reference_present' => 'BFM itinerary reference present',
            'bfm_pricing_information_index_present' => 'BFM pricing information index present',
            'bfm_pricing_information_index' => 'BFM pricing information index',
            'formal_offer_reference_required' => 'Formal offer reference required',
            'formal_pricing_information_ref_required' => 'Formal pricing information ref required',
            'shop_identifiers_present' => 'Shop identifiers present',
            'pricing_information_ref_present' => 'Pricing information ref present',
            'offer_reference_present' => 'Offer reference present',
            'itinerary_reference_present' => 'Itinerary reference present',
            'pricing_linkage_source' => 'Pricing linkage source',
            'context_can_be_rebuilt' => 'Context can be rebuilt',
            'controlled_certification_required' => 'Controlled certification required',
            'blocker_reasons' => 'Blocker reasons',
            'airline_segment_status' => 'Airline segment status returned',
            'affected_flight_numbers' => 'Affected flight numbers',
            'halt_on_status_received' => 'HaltOnStatus received',
            'host_sell_reject_suggested_action' => 'Host sell reject - suggested action',
        ];

        $rows = [];
        foreach ($labels as $key => $label) {
            $value = $fields[$key] ?? 'Not recorded yet';
            $rows[] = [
                'label' => $label,
                'value' => $value,
                'badge' => $this->sabreReadinessBadgeForField($key, $value),
            ];
        }

        $rows[] = [
            'label' => 'Last attempt time',
            'value' => $lastAttemptAt ?? 'Not recorded yet',
            'badge' => $lastAttemptAt !== null ? null : 'Not attempted',
        ];
        $rows[] = [
            'label' => 'Latest PNR create attempt',
            'value' => $createAttemptLabel ?? 'Not recorded yet',
            'badge' => $createAttemptLabel !== null ? null : 'Not attempted',
        ];
        $rows[] = [
            'label' => 'Latest PNR sync attempt',
            'value' => $syncAttemptLabel ?? 'Not recorded yet',
            'badge' => $syncAttemptLabel !== null ? null : 'Not attempted',
        ];
        $rows[] = [
            'label' => 'Latest ticketing attempt',
            'value' => $ticketingAttemptLabel ?? 'Not recorded yet',
            'badge' => $ticketingAttemptLabel !== null ? null : 'Not attempted',
        ];

        if (trim((string) ($booking->pnr ?? '')) !== '' && ($fields['pnr_locator'] ?? 'Not recorded yet') === 'Not recorded yet') {
            foreach ($rows as $i => $row) {
                if ($row['label'] === 'PNR / locator') {
                    $rows[$i]['value'] = (string) $booking->pnr;
                    $rows[$i]['badge'] = 'Ready';
                    break;
                }
            }
        }

        return $rows;
    }

    protected function sabreReadinessBadgeForField(string $key, string $value): ?string
    {
        if ($value === 'Not recorded yet') {
            return $key === 'pnr_attempted' ? 'Not attempted' : null;
        }

        return match ($key) {
            'pcc_present', 'target_city_present', 'rbd_coverage', 'fare_basis_coverage', 'cpnr_required_blocks_present' => $value === 'Yes' || str_contains($value, 'Present') || str_contains($value, 'complete') ? 'Ready' : ($value === 'No' || str_contains($value, 'Missing') ? 'Missing' : null),
            'revalidation_skipped' => $value === 'Yes' ? 'Skipped by strategy' : null,
            'iati_like_selected' => $value === 'Yes' ? 'Selected' : null,
            'manual_review_required' => $value === 'Yes' ? 'Requires review' : ($value === 'No' ? 'Ready' : null),
            'pnr_attempted' => $value === 'Yes' ? 'Selected' : 'Not attempted',
            'pnr_created' => $value === 'Yes' ? 'Ready' : null,
            'cpnr_required_blocks_missing' => $value !== '-' && $value !== 'Not recorded yet' ? 'Missing' : null,
            'multi_segment_candidate' => $value === 'Yes' ? 'Multi-segment' : null,
            'iati_like_multi_segment_ready' => $value === 'Yes' ? 'Wire ready' : ($value === 'No' ? 'Not ready' : null),
            'mixed_carrier' => $value === 'Yes' ? 'Mixed' : null,
            'multi_segment_blocker_reasons' => $value !== '-' && $value !== 'Not recorded yet' ? 'Blocked' : null,
            'proposed_certification_category' => str_contains($value, 'same_carrier') ? 'Same carrier' : (str_contains($value, 'mixed') ? 'Mixed' : null),
            default => null,
        };
    }

    protected function formatSabreDiagnosticScalar(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Not recorded yet';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_scalar($value)) {
            return Str::limit(trim((string) $value), 120, '...');
        }

        return 'Not recorded yet';
    }

    protected function formatSabreYesNo(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Not recorded yet';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_scalar($value)) {
            $s = strtolower(trim((string) $value));
            if (in_array($s, ['1', 'true', 'yes'], true)) {
                return 'Yes';
            }
            if (in_array($s, ['0', 'false', 'no'], true)) {
                return 'No';
            }
        }

        return 'Not recorded yet';
    }

    protected function formatPccFingerprint(mixed $last2, mixed $hash): string
    {
        $l2 = is_scalar($last2) ? trim((string) $last2) : '';
        $h = is_scalar($hash) ? trim((string) $hash) : '';
        if ($l2 !== '' && $h !== '') {
            return '...'.$l2.' / '.$h;
        }
        if ($l2 !== '') {
            return '...'.$l2;
        }
        if ($h !== '') {
            return $h;
        }

        return 'Not recorded yet';
    }

    /**
     * @param  array<string, mixed>  $merged
     */
    protected function formatRbdCoverage(array $merged): string
    {
        if (($merged['rbd_complete'] ?? null) === true) {
            return 'Present (complete)';
        }
        if (($merged['rbd_complete'] ?? null) === false) {
            return 'Missing';
        }
        $total = (int) ($merged['rbd_total_segments'] ?? 0);
        $present = (int) ($merged['rbd_present_count'] ?? 0);
        if ($total > 0) {
            return $present >= $total ? 'Present (complete)' : "Partial ({$present}/{$total})";
        }

        return 'Not recorded yet';
    }

    /**
     * @param  array<string, mixed>  $merged
     */
    protected function formatFareBasisCoverage(array $merged): string
    {
        if (($merged['has_fare_basis'] ?? null) === true) {
            return 'Present';
        }
        if (($merged['has_fare_basis'] ?? null) === false) {
            return 'Missing';
        }
        $total = (int) ($merged['rbd_total_segments'] ?? $merged['segment_count'] ?? 0);
        $present = (int) ($merged['fare_basis_present_count'] ?? 0);
        $missing = (int) ($merged['fare_basis_missing_count'] ?? 0);
        if ($total > 0 && $missing === 0 && $present > 0) {
            return 'Present (complete)';
        }
        if ($total > 0 && $missing > 0) {
            return "Partial ({$present}/{$total})";
        }

        return 'Not recorded yet';
    }

    protected function formatBlockList(mixed $blocks): string
    {
        if (! is_array($blocks) || $blocks === []) {
            return '-';
        }
        $safe = [];
        foreach ($blocks as $block) {
            if (! is_scalar($block)) {
                continue;
            }
            $s = trim((string) $block);
            if ($s !== '' && ! $this->isBlockedSabreDiagnosticKey($s)) {
                $safe[] = str_replace('_', ' ', $s);
            }
        }

        return $safe === [] ? '-' : implode(', ', array_slice($safe, 0, 12));
    }

    public function summarizeFailure(?string $message): string
    {
        $safe = (string) $message;
        $safe = preg_replace('/(password|secret|token|api[_-]?key)\s*[:=]\s*[^\s,;]+/i', '$1=[REDACTED]', $safe) ?? '';
        $safe = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '[REDACTED_EMAIL]', $safe) ?? '';
        $safe = preg_replace('/\b\d{8,}\b/', '[REDACTED_NUMBER]', $safe) ?? '';

        return Str::limit(trim($safe), 180, '...');
    }

    /**
     * @return list<array{label: string, value: string, badge: ?string}>
     */
    protected function supplierStrategyPanelRows(Booking $booking): array
    {
        try {
            $provider = SupplierProvider::Sabre->value;
            $selection = app(SupplierActionStrategySelector::class)->selectForBooking(
                $booking,
                $provider,
                SupplierActionCode::CREATE_PNR,
            );
            $summary = app(SupplierActionStrategyDigest::class)->buildBookingSummary(
                $booking,
                $provider,
                SupplierActionCode::CREATE_PNR,
            );
            $validation = app(SupplierPnrValidationSummary::class)->build($booking);
            $flags = app(SupplierPnrFlagGate::class)->sabreFlags();
            $selected = (string) ($selection['selected_strategy'] ?? '');
            $candidates = app(SupplierActionStrategyDigest::class)->buildCandidateDigests(
                $booking,
                $provider,
                SupplierActionCode::CREATE_PNR,
                $selection,
            );
            $certStatus = '-';
            foreach ($candidates as $candidate) {
                if (($candidate['strategy_code'] ?? '') === $selected) {
                    $certStatus = (string) ($candidate['certification_status'] ?? '-');
                    break;
                }
            }

            return [
                ['label' => 'Strategy selected', 'value' => $selected !== '' ? $selected : 'None', 'badge' => $selected !== '' ? 'info' : 'blocked'],
                ['label' => 'Strategy certification', 'value' => $certStatus, 'badge' => null],
                ['label' => 'Selection reason', 'value' => (string) ($selection['selection_reason'] ?? '-'), 'badge' => null],
                ['label' => 'Fallback available', 'value' => ($selection['fallback_available'] ?? false) ? 'Yes (admin confirm)' : 'No', 'badge' => null],
                ['label' => 'PNR create enabled', 'value' => ($flags['pnr_create_enabled'] ?? false) ? 'Yes' : 'No', 'badge' => null],
                ['label' => 'Ticketing enabled', 'value' => ($flags['ticketing_enabled'] ?? false) ? 'Yes' : 'No', 'badge' => 'info'],
                ['label' => 'Brand context consistent', 'value' => ($summary['selected_brand_context_consistent'] ?? false) ? 'Yes' : 'No', 'badge' => null],
                ['label' => 'PNR validation', 'value' => ($validation['pnr_created'] ?? false) ? 'Created' : 'Not created', 'badge' => null],
            ];
        } catch (\Throwable) {
            return [];
        }
    }
}
