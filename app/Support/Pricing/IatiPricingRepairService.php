<?php

namespace App\Support\Pricing;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingFareBreakdown;
use App\Models\BookingHoldSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Safe repair for IATI bookings with inflated PKR totals from mistaken USD conversion,
 * plus residual hold-session relink and fare-breakdown JSON correction after pricing repair.
 */
class IatiPricingRepairService
{
    /**
     * @return array<string, mixed>
     */
    public function audit(Booking $booking): array
    {
        $booking->loadMissing(['fareBreakdown', 'holdSession', 'supplierBookingAttempts', 'payments']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $pricingSnapshot = is_array($meta['pricing_snapshot'] ?? null) ? $meta['pricing_snapshot'] : [];
        $passengerPricing = is_array($meta['passenger_pricing'] ?? null)
            ? $meta['passenger_pricing']
            : (is_array($pricingSnapshot['passenger_pricing'] ?? null) ? $pricingSnapshot['passenger_pricing'] : []);
        $passengerTotals = IatiFarePricingResolver::passengerPricingTotals($passengerPricing);
        $expectedTotal = $this->resolveExpectedTotalPkr($meta, $pricingSnapshot, $passengerTotals);
        $storedSelected = (float) ($booking->selected_fare_total ?? 0);
        $storedRevalidated = (float) ($booking->revalidated_fare_total ?? 0);
        $storedBreakdownTotal = (float) ($booking->fareBreakdown?->total ?? 0);
        $storedTotal = max($storedSelected, $storedRevalidated, $storedBreakdownTotal, (float) ($pricingSnapshot['final_total'] ?? 0));
        $doubleConversion = IatiFarePricingResolver::detectPersistedDoubleConversion(
            $expectedTotal,
            $storedTotal,
            $pricingSnapshot,
            $meta,
        );
        $holdSessions = $this->holdSessionAudit($booking, $meta);
        $residualHold = $this->residualHoldSessionAudit($booking, $meta);
        $nestedBreakdown = $this->nestedBreakdownAudit($booking);
        $commonBlockers = $this->commonRepairBlockers($booking);
        $safeRepairAvailable = $this->canRepair($booking, $doubleConversion);
        $residualRepairAvailable = $commonBlockers === []
            && ($residualHold['hold_session_relink_available'] || $nestedBreakdown['nested_breakdown_repair_available']);

        $amounts = $this->resolveCorrectedAmounts($booking, $expectedTotal, $doubleConversion);
        $plannedResidual = $residualRepairAvailable
            ? $this->planResidualChanges($booking, $residualHold, $nestedBreakdown, $amounts)
            : [];

        return [
            'booking_id' => $booking->id,
            'provider' => strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? ''))),
            'selected_display_total' => $expectedTotal > 0 ? $expectedTotal : null,
            'passenger_pricing_total' => $passengerTotals['total'] ?? null,
            'stored_selected_fare_total' => $storedSelected > 0 ? $storedSelected : null,
            'stored_revalidated_fare_total' => $storedRevalidated > 0 ? $storedRevalidated : null,
            'stored_fare_breakdown_total' => $storedBreakdownTotal > 0 ? $storedBreakdownTotal : null,
            'detected_double_conversion' => $doubleConversion !== null,
            'expected_total_pkr' => $expectedTotal > 0 ? $expectedTotal : null,
            'stored_total_pkr' => $storedTotal > 0 ? $storedTotal : null,
            'currency_source_used' => (string) ($doubleConversion['currency_source_used'] ?? 'fare_breakdown'),
            'safe_repair_available' => $safeRepairAvailable,
            'repair_blockers' => $this->repairBlockers($booking, $doubleConversion),
            'repairable_hold_session_ids' => $holdSessions['repairable'],
            'report_only_hold_session_ids' => $holdSessions['report_only'],
            'linked_hold_session_id' => $residualHold['linked_hold_session_id'],
            'linked_hold_session_matches_booking' => $residualHold['linked_hold_session_matches_booking'],
            'candidate_orphan_hold_session_ids' => $residualHold['candidate_orphan_hold_session_ids'],
            'hold_session_link_available' => $residualHold['hold_session_link_available'],
            'hold_session_relink_available' => $residualHold['hold_session_relink_available'],
            'stale_hold_session_id' => $residualHold['stale_hold_session_id'],
            'nested_breakdown_repair_available' => $nestedBreakdown['nested_breakdown_repair_available'],
            'residual_repair_available' => $residualRepairAvailable,
            'residual_repair_blockers' => $this->residualRepairBlockers($booking, $residualHold, $nestedBreakdown),
            'relink_blockers' => $residualHold['relink_blockers'],
            'planned_residual_changes' => $plannedResidual,
        ];
    }

    /**
     * @return array{applied: bool, changes: array<string, mixed>, planned_residual_changes: array<string, mixed>, blockers: list<string>}
     */
    public function repair(Booking $booking, bool $apply = false): array
    {
        $audit = $this->audit($booking);
        $safeRepairAvailable = (bool) ($audit['safe_repair_available'] ?? false);
        $residualRepairAvailable = (bool) ($audit['residual_repair_available'] ?? false);

        if (! $safeRepairAvailable && ! $residualRepairAvailable) {
            $blockers = array_values(array_unique(array_merge(
                is_array($audit['repair_blockers'] ?? null) ? $audit['repair_blockers'] : [],
                is_array($audit['residual_repair_blockers'] ?? null) ? $audit['residual_repair_blockers'] : [],
            )));

            return [
                'applied' => false,
                'changes' => [],
                'planned_residual_changes' => [],
                'blockers' => $blockers,
            ];
        }

        $booking->loadMissing(['fareBreakdown', 'holdSession']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $pricingSnapshot = is_array($meta['pricing_snapshot'] ?? null) ? $meta['pricing_snapshot'] : [];
        $doubleConversion = IatiFarePricingResolver::detectPersistedDoubleConversion(
            (float) ($audit['expected_total_pkr'] ?? 0),
            (float) ($audit['stored_total_pkr'] ?? 0),
            $pricingSnapshot,
            $meta,
        );
        $amounts = $this->resolveCorrectedAmounts($booking, (float) ($audit['expected_total_pkr'] ?? 0), $doubleConversion);
        $expectedTotal = $amounts['total'];
        $expectedBase = $amounts['base'];
        $expectedTax = $amounts['tax'];

        $changes = [];
        $plannedResidual = is_array($audit['planned_residual_changes'] ?? null) ? $audit['planned_residual_changes'] : [];

        if ($safeRepairAvailable) {
            $correctedPricing = IatiFarePricingResolver::correctDoubleConversion($pricingSnapshot, [
                'expected_total_pkr' => $expectedTotal,
                'expected_base_pkr' => $expectedBase,
                'expected_tax_pkr' => $expectedTax,
                'source_total' => (float) ($pricingSnapshot['supplier_total_source'] ?? $expectedTotal),
                'fx_rate' => (float) ($pricingSnapshot['fx_rate'] ?? 0),
                'inflated_total' => (float) ($audit['stored_total_pkr'] ?? 0),
            ]);
            $correctedPricing['supplier_total'] = $expectedTotal;
            $correctedPricing['final_total'] = $expectedTotal;

            $repairableHoldIds = is_array($audit['repairable_hold_session_ids'] ?? null)
                ? array_values(array_map('intval', $audit['repairable_hold_session_ids']))
                : [];
            if ($residualRepairAvailable && (bool) ($audit['hold_session_relink_available'] ?? false)) {
                $staleId = (int) ($audit['stale_hold_session_id'] ?? 0);
                $repairableHoldIds = array_values(array_filter(
                    $repairableHoldIds,
                    fn (int $id): bool => $id !== $staleId,
                ));
            }

            $changes = [
                'bookings.selected_fare_total' => $expectedTotal,
                'bookings.revalidated_fare_total' => $expectedTotal,
                'booking_fare_breakdowns.base_fare' => $expectedBase,
                'booking_fare_breakdowns.taxes' => $expectedTax,
                'booking_fare_breakdowns.total' => $expectedTotal,
                'booking_fare_breakdowns.currency' => 'PKR',
                'meta.pricing_snapshot.supplier_total' => $expectedTotal,
                'meta.pricing_snapshot.final_total' => $expectedTotal,
                'meta.pricing_snapshot.supplier_currency' => 'PKR',
                'meta.pricing_snapshot.pricing_currency' => 'PKR',
                'meta.pricing_snapshot.conversion_status' => 'same_currency',
                'meta.pricing_snapshot.fx_rate' => 1,
                'meta.supplier_total' => $expectedTotal,
                'meta.supplier_currency' => 'PKR',
                'repairable_hold_session_ids' => $repairableHoldIds,
            ];

            foreach ($repairableHoldIds as $holdSessionId) {
                $changes['booking_hold_sessions.'.$holdSessionId.'.validated_total_amount'] = $expectedTotal;
                $changes['booking_hold_sessions.'.$holdSessionId.'.validated_total_currency'] = 'PKR';
                $changes['booking_hold_sessions.'.$holdSessionId.'.converted_total_pkr'] = $expectedTotal;
            }
        }

        if ($residualRepairAvailable) {
            $changes = array_merge($changes, $this->flattenPlannedResidualChanges($plannedResidual));
        }

        if (! $apply) {
            return [
                'applied' => false,
                'changes' => $changes,
                'planned_residual_changes' => $plannedResidual,
                'blockers' => [],
            ];
        }

        DB::transaction(function () use (
            $booking,
            $safeRepairAvailable,
            $residualRepairAvailable,
            $audit,
            $plannedResidual,
            $expectedTotal,
            $expectedBase,
            $expectedTax,
            $meta,
            $pricingSnapshot,
            $changes,
        ): void {
            if ($safeRepairAvailable) {
                $correctedPricing = IatiFarePricingResolver::correctDoubleConversion($pricingSnapshot, [
                    'expected_total_pkr' => $expectedTotal,
                    'expected_base_pkr' => $expectedBase,
                    'expected_tax_pkr' => $expectedTax,
                    'source_total' => (float) ($pricingSnapshot['supplier_total_source'] ?? $expectedTotal),
                    'fx_rate' => (float) ($pricingSnapshot['fx_rate'] ?? 0),
                    'inflated_total' => (float) ($audit['stored_total_pkr'] ?? 0),
                ]);
                $correctedPricing['supplier_total'] = $expectedTotal;
                $correctedPricing['final_total'] = $expectedTotal;

                $meta['pricing_snapshot'] = array_merge(
                    is_array($meta['pricing_snapshot'] ?? null) ? $meta['pricing_snapshot'] : [],
                    $correctedPricing,
                );
                $meta['supplier_total'] = $expectedTotal;
                $meta['supplier_currency'] = 'PKR';

                $booking->forceFill([
                    'selected_fare_total' => $expectedTotal,
                    'revalidated_fare_total' => $expectedTotal,
                    'meta' => $meta,
                ])->save();

                BookingFareBreakdown::query()->updateOrCreate(
                    ['booking_id' => $booking->id],
                    [
                        'base_fare' => $expectedBase,
                        'taxes' => $expectedTax,
                        'total' => $expectedTotal,
                        'currency' => 'PKR',
                    ],
                );

                $repairableHoldIds = is_array($changes['repairable_hold_session_ids'] ?? null)
                    ? $changes['repairable_hold_session_ids']
                    : [];
                if ($repairableHoldIds !== []) {
                    BookingHoldSession::query()
                        ->whereIn('id', $repairableHoldIds)
                        ->update([
                            'validated_total_amount' => $expectedTotal,
                            'validated_total_currency' => 'PKR',
                            'converted_total_pkr' => $expectedTotal,
                        ]);
                }
            }

            if ($residualRepairAvailable) {
                $this->applyResidualChanges($booking, $plannedResidual, $expectedTotal, $expectedBase, $expectedTax);
            }
        });

        return [
            'applied' => true,
            'changes' => $changes,
            'planned_residual_changes' => $plannedResidual,
            'blockers' => [],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $passengerTotals
     */
    protected function resolveExpectedTotalPkr(array $meta, array $pricingSnapshot, ?array $passengerTotals): float
    {
        if (is_array($passengerTotals) && (float) ($passengerTotals['total'] ?? 0) > 0) {
            return (float) $passengerTotals['total'];
        }

        $family = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
        foreach ([
            $family['displayed_price'] ?? null,
            $family['price_total'] ?? null,
            $family['price'] ?? null,
        ] as $candidate) {
            $value = (float) ($candidate ?? 0);
            if ($value > 0) {
                return $value;
            }
        }

        foreach ([
            $pricingSnapshot['supplier_total_source'] ?? null,
            $meta['supplier_total'] ?? null,
            data_get($meta, 'flight_offer_snapshot.supplier_total_source'),
            data_get($meta, 'validated_offer_snapshot.fare_breakdown.supplier_total'),
        ] as $candidate) {
            $value = (float) ($candidate ?? 0);
            if ($value > 0) {
                return $value;
            }
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>|null  $doubleConversion
     * @return array{total: float, base: float, tax: float}
     */
    protected function resolveCorrectedAmounts(Booking $booking, float $expectedTotal, ?array $doubleConversion): array
    {
        if ($doubleConversion !== null) {
            return [
                'total' => (float) ($doubleConversion['expected_total_pkr'] ?? $expectedTotal),
                'base' => (float) ($doubleConversion['expected_base_pkr'] ?? $expectedTotal),
                'tax' => (float) ($doubleConversion['expected_tax_pkr'] ?? 0),
            ];
        }

        $breakdown = $booking->fareBreakdown;
        $total = (float) ($breakdown?->total ?? 0);
        if ($total <= 0.0) {
            $total = max(
                (float) ($booking->selected_fare_total ?? 0),
                (float) ($booking->revalidated_fare_total ?? 0),
                $expectedTotal,
            );
        }

        return [
            'total' => $total,
            'base' => (float) ($breakdown?->base_fare ?? $total),
            'tax' => (float) ($breakdown?->taxes ?? 0),
        ];
    }

    /**
     * @return array{repairable: list<int>, report_only: list<int>}
     */
    protected function holdSessionAudit(Booking $booking, array $meta): array
    {
        $repairable = [];
        if ($booking->hold_session_id !== null) {
            $repairable[] = (int) $booking->hold_session_id;
        }

        $linked = BookingHoldSession::query()
            ->where('booking_id', $booking->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $repairable = array_values(array_unique([...$repairable, ...$linked]));

        $reportOnly = [];
        $searchId = trim((string) ($meta['checkout_search_id'] ?? ''));
        $offerId = trim((string) ($meta['checkout_offer_id'] ?? $meta['original_offer_id'] ?? ''));
        if ($searchId !== '' && $offerId !== '') {
            $orphans = BookingHoldSession::query()
                ->whereNull('booking_id')
                ->where('search_id', $searchId)
                ->where('offer_id', $offerId)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $reportOnly = array_values(array_diff($orphans, $repairable));
        }

        return [
            'repairable' => $repairable,
            'report_only' => $reportOnly,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function residualHoldSessionAudit(Booking $booking, array $meta): array
    {
        $linkedHoldSessionId = $booking->hold_session_id !== null ? (int) $booking->hold_session_id : null;
        $linkedHold = $booking->holdSession;
        $searchId = trim((string) ($meta['checkout_search_id'] ?? ''));
        $offerId = trim((string) ($meta['checkout_offer_id'] ?? $meta['original_offer_id'] ?? ''));
        $matches = $this->holdSessionMatchesMeta($linkedHold, $searchId, $offerId);

        $candidates = ($searchId !== '' && $offerId !== '')
            ? BookingHoldSession::query()
                ->whereNull('booking_id')
                ->where('search_id', $searchId)
                ->where('offer_id', $offerId)
                ->orderBy('id')
                ->get()
            : collect();

        $candidateIds = $candidates
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $relinkBlockers = $this->holdRelinkBlockers($booking, $linkedHold, $searchId, $offerId, $matches, $candidates);
        $staleHoldSessionId = ($linkedHoldSessionId !== null && ! $matches) ? $linkedHoldSessionId : null;
        $orphanLinkAvailable = $linkedHold === null && $relinkBlockers === [];
        $staleRelinkAvailable = $staleHoldSessionId !== null && $relinkBlockers === [];
        $holdSessionRelinkAvailable = $orphanLinkAvailable || $staleRelinkAvailable;

        return [
            'linked_hold_session_id' => $linkedHoldSessionId,
            'linked_hold_session_matches_booking' => $matches,
            'candidate_orphan_hold_session_ids' => $candidateIds,
            'hold_session_link_available' => $orphanLinkAvailable,
            'hold_session_relink_available' => $holdSessionRelinkAvailable,
            'stale_hold_session_id' => $staleHoldSessionId,
            'relink_blockers' => $relinkBlockers,
        ];
    }

    /**
     * @return array{nested_breakdown_repair_available: bool}
     */
    protected function nestedBreakdownAudit(Booking $booking): array
    {
        $breakdown = $booking->fareBreakdown;
        if ($breakdown === null) {
            return ['nested_breakdown_repair_available' => false];
        }

        return [
            'nested_breakdown_repair_available' => $this->nestedBreakdownNeedsRepair($breakdown),
        ];
    }

    protected function holdSessionMatchesMeta(?BookingHoldSession $hold, string $searchId, string $offerId): bool
    {
        if ($hold === null) {
            return false;
        }
        if ($searchId === '' || $offerId === '') {
            return true;
        }

        return trim((string) $hold->search_id) === $searchId
            && trim((string) $hold->offer_id) === $offerId;
    }

    /**
     * @param  Collection<int, BookingHoldSession>  $candidates
     * @return list<string>
     */
    protected function holdRelinkBlockers(
        Booking $booking,
        ?BookingHoldSession $linkedHold,
        string $searchId,
        string $offerId,
        bool $matches,
        $candidates,
    ): array {
        $blockers = [];

        if ($linkedHold !== null) {
            if ($matches) {
                $blockers[] = 'linked_hold_session_matches';
            } elseif ((int) ($linkedHold->booking_id ?? 0) !== (int) $booking->id) {
                $blockers[] = 'linked_hold_session_booking_mismatch';
            }
        }

        if ($searchId === '' || $offerId === '') {
            $blockers[] = 'checkout_identity_missing';
        }

        if ($candidates->count() === 0) {
            $blockers[] = 'no_orphan_candidate';
        } elseif ($candidates->count() > 1) {
            $blockers[] = 'multiple_orphan_candidates';
        } else {
            /** @var BookingHoldSession $candidate */
            $candidate = $candidates->first();
            if (strtolower(trim((string) $candidate->supplier_provider)) !== SupplierProvider::Iati->value) {
                $blockers[] = 'candidate_not_iati';
            }
            if (trim((string) ($candidate->supplier_order_id ?? '')) !== '') {
                $blockers[] = 'candidate_supplier_order_id_present';
            }
            if (trim((string) ($candidate->supplier_order_reference ?? '')) !== '') {
                $blockers[] = 'candidate_supplier_order_reference_present';
            }
        }

        return $blockers;
    }

    protected function nestedBreakdownNeedsRepair(BookingFareBreakdown $breakdown): bool
    {
        $rows = is_array($breakdown->breakdown) ? $breakdown->breakdown : [];
        if ($rows === []) {
            return false;
        }

        $baseColumn = (float) $breakdown->base_fare;
        $taxColumn = (float) $breakdown->taxes;
        $needsRepair = false;

        foreach ($rows as $row) {
            if (! is_array($row) || ! array_key_exists('label', $row)) {
                continue;
            }
            $label = trim((string) $row['label']);
            $amount = (float) ($row['amount'] ?? 0);
            if ($label === 'Base fare' && abs($amount - $baseColumn) > 0.01) {
                $needsRepair = true;
            }
            if ($label === 'Taxes & surcharges' && abs($amount - $taxColumn) > 0.01) {
                $needsRepair = true;
            }
        }

        return $needsRepair;
    }

    /**
     * @param  array<string, mixed>  $residualHold
     * @param  array{nested_breakdown_repair_available: bool}  $nestedBreakdown
     * @param  array{total: float, base: float, tax: float}  $amounts
     * @return array<string, mixed>
     */
    protected function planResidualChanges(Booking $booking, array $residualHold, array $nestedBreakdown, array $amounts): array
    {
        $planned = [];

        if ((bool) ($residualHold['hold_session_relink_available'] ?? false)) {
            $candidateId = (int) ($residualHold['candidate_orphan_hold_session_ids'][0] ?? 0);
            $staleId = (int) ($residualHold['stale_hold_session_id'] ?? 0);
            $snapshots = $this->buildHoldSessionPricingSnapshots($amounts['total'], $amounts['base'], $amounts['tax']);

            $planned['bookings.hold_session_id'] = $candidateId;
            $planned['booking_hold_sessions.'.$candidateId.'.booking_id'] = (int) $booking->id;
            $planned['booking_hold_sessions.'.$candidateId.'.validated_total_amount'] = $amounts['total'];
            $planned['booking_hold_sessions.'.$candidateId.'.validated_total_currency'] = 'PKR';
            $planned['booking_hold_sessions.'.$candidateId.'.converted_total_pkr'] = $amounts['total'];
            $planned['booking_hold_sessions.'.$candidateId.'.markup_snapshot'] = $snapshots['markup_snapshot'];
            $planned['booking_hold_sessions.'.$candidateId.'.validated_offer_snapshot'] = $snapshots['validated_offer_snapshot'];
            if ($staleId > 0) {
                $planned['booking_hold_sessions.'.$staleId.'.booking_id'] = null;
            }
        }

        if ((bool) ($nestedBreakdown['nested_breakdown_repair_available'] ?? false)) {
            $planned['booking_fare_breakdowns.breakdown'] = $this->correctedBreakdownJson(
                is_array($booking->fareBreakdown?->breakdown) ? $booking->fareBreakdown->breakdown : [],
                $amounts['base'],
                $amounts['tax'],
            );
        }

        return $planned;
    }

    /**
     * @return array{markup_snapshot: array<string, mixed>, validated_offer_snapshot: array<string, mixed>}
     */
    protected function buildHoldSessionPricingSnapshots(float $total, float $base, float $tax): array
    {
        $pricingComponents = [
            'supplier_total' => $total,
            'final_total' => $total,
            'supplier_currency' => 'PKR',
            'pricing_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'fx_rate' => 1,
        ];

        return [
            'markup_snapshot' => $pricingComponents,
            'validated_offer_snapshot' => [
                'total' => $total,
                'currency' => 'PKR',
                'fare_breakdown' => [
                    'base_fare' => $base,
                    'taxes' => $tax,
                    'supplier_total' => $total,
                    'currency' => 'PKR',
                ],
                'pricing_components' => $pricingComponents,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function correctedBreakdownJson(array $rows, float $base, float $tax): array
    {
        $corrected = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                $corrected[] = $row;

                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === 'Base fare') {
                $row['amount'] = $base;
            } elseif ($label === 'Taxes & surcharges') {
                $row['amount'] = $tax;
            }
            $corrected[] = $row;
        }

        return $corrected;
    }

    /**
     * @param  array<string, mixed>  $plannedResidual
     * @return array<string, mixed>
     */
    protected function flattenPlannedResidualChanges(array $plannedResidual): array
    {
        $flat = [];
        foreach ($plannedResidual as $key => $value) {
            $flat[(string) $key] = $value;
        }

        return $flat;
    }

    /**
     * @param  array<string, mixed>  $plannedResidual
     */
    protected function applyResidualChanges(
        Booking $booking,
        array $plannedResidual,
        float $expectedTotal,
        float $expectedBase,
        float $expectedTax,
    ): void {
        if (array_key_exists('bookings.hold_session_id', $plannedResidual)) {
            $candidateId = (int) $plannedResidual['bookings.hold_session_id'];
            $candidate = BookingHoldSession::query()->find($candidateId);
            if ($candidate !== null) {
                $existingValidated = is_array($candidate->validated_offer_snapshot)
                    ? $candidate->validated_offer_snapshot
                    : [];
                $existingFare = is_array($existingValidated['fare_breakdown'] ?? null)
                    ? $existingValidated['fare_breakdown']
                    : [];
                $snapshots = $this->buildHoldSessionPricingSnapshots($expectedTotal, $expectedBase, $expectedTax);
                $validatedOfferSnapshot = array_merge($existingValidated, $snapshots['validated_offer_snapshot']);
                $validatedOfferSnapshot['fare_breakdown'] = array_merge(
                    $existingFare,
                    $snapshots['validated_offer_snapshot']['fare_breakdown'],
                );
                $existingPricing = is_array($existingValidated['pricing_components'] ?? null)
                    ? $existingValidated['pricing_components']
                    : [];
                $validatedOfferSnapshot['pricing_components'] = array_merge(
                    $existingPricing,
                    $snapshots['validated_offer_snapshot']['pricing_components'],
                );

                $candidate->forceFill([
                    'booking_id' => (int) $booking->id,
                    'validated_total_amount' => $expectedTotal,
                    'validated_total_currency' => 'PKR',
                    'converted_total_pkr' => $expectedTotal,
                    'markup_snapshot' => $snapshots['markup_snapshot'],
                    'validated_offer_snapshot' => $validatedOfferSnapshot,
                ])->save();

                $booking->forceFill(['hold_session_id' => $candidateId])->save();
            }

            foreach ($plannedResidual as $key => $value) {
                if (! str_starts_with((string) $key, 'booking_hold_sessions.') || ! str_ends_with((string) $key, '.booking_id')) {
                    continue;
                }
                if ($value !== null) {
                    continue;
                }
                $holdId = (int) str_replace(['booking_hold_sessions.', '.booking_id'], '', (string) $key);
                if ($holdId <= 0) {
                    continue;
                }
                BookingHoldSession::query()
                    ->whereKey($holdId)
                    ->where('booking_id', $booking->id)
                    ->update(['booking_id' => null]);
            }
        }

        if (array_key_exists('booking_fare_breakdowns.breakdown', $plannedResidual)) {
            $breakdownRows = $plannedResidual['booking_fare_breakdowns.breakdown'];
            if (! is_array($breakdownRows)) {
                $breakdownRows = $this->correctedBreakdownJson(
                    is_array($booking->fareBreakdown?->breakdown) ? $booking->fareBreakdown->breakdown : [],
                    $expectedBase,
                    $expectedTax,
                );
            }

            BookingFareBreakdown::query()->updateOrCreate(
                ['booking_id' => $booking->id],
                ['breakdown' => $breakdownRows],
            );
        }
    }

    /**
     * @return list<string>
     */
    public function commonRepairBlockers(Booking $booking): array
    {
        $blockers = [];
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        if ($provider !== SupplierProvider::Iati->value) {
            $blockers[] = 'not_iati';
        }
        if (trim((string) ($booking->supplier_reference ?? '')) !== '') {
            $blockers[] = 'supplier_reference_present';
        }
        if (trim((string) ($booking->supplier_api_booking_id ?? '')) !== '') {
            $blockers[] = 'supplier_api_booking_id_present';
        }
        if (trim((string) ($booking->pnr ?? '')) !== '') {
            $blockers[] = 'pnr_present';
        }
        if ((string) ($booking->payment_status ?? 'unpaid') !== 'unpaid') {
            $blockers[] = 'payment_not_unpaid';
        }
        if ($booking->supplierBookingAttempts()->exists()) {
            $blockers[] = 'supplier_attempts_present';
        }
        if ($booking->payments()->exists()) {
            $blockers[] = 'payments_present';
        }

        return $blockers;
    }

    /**
     * @param  array<string, mixed>|null  $doubleConversion
     */
    public function canRepair(Booking $booking, ?array $doubleConversion): bool
    {
        return $this->repairBlockers($booking, $doubleConversion) === [];
    }

    /**
     * @param  array<string, mixed>|null  $doubleConversion
     * @return list<string>
     */
    public function repairBlockers(Booking $booking, ?array $doubleConversion): array
    {
        $blockers = $this->commonRepairBlockers($booking);
        if ($doubleConversion === null) {
            $blockers[] = 'double_conversion_not_detected';
        }

        return $blockers;
    }

    /**
     * @param  array<string, mixed>  $residualHold
     * @param  array{nested_breakdown_repair_available: bool}  $nestedBreakdown
     * @return list<string>
     */
    public function residualRepairBlockers(Booking $booking, array $residualHold, array $nestedBreakdown): array
    {
        $blockers = $this->commonRepairBlockers($booking);

        $holdRelinkAvailable = (bool) ($residualHold['hold_session_relink_available'] ?? false);
        $nestedAvailable = (bool) ($nestedBreakdown['nested_breakdown_repair_available'] ?? false);

        if (! $holdRelinkAvailable) {
            $blockers = array_values(array_unique(array_merge(
                $blockers,
                is_array($residualHold['relink_blockers'] ?? null) ? $residualHold['relink_blockers'] : [],
            )));
        }

        if (! $holdRelinkAvailable && ! $nestedAvailable && $blockers === []) {
            $blockers[] = 'no_residual_work';
        }

        return array_values(array_unique($blockers));
    }
}
