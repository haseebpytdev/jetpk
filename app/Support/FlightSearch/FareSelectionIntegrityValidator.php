<?php

namespace App\Support\FlightSearch;

use App\Data\SelectedFareContext;
use App\Data\SelectedFareOption;

/**
 * Supplier-neutral branded fare selection integrity checks before PNR/order creation.
 */
final class FareSelectionIntegrityValidator
{
    public const REASON_MISMATCH = 'branded_fare_context_mismatch';

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>|null  $offerSnapshot
     * @param  array<string, mixed>|null  $validatedSnapshot
     * @param  array<string, mixed>|null  $payloadDigest
     * @return array{
     *     consistent: bool,
     *     reason_code: string|null,
     *     mismatch_fields: list<string>,
     *     admin_summary: array<string, mixed>,
     *     blocks_pnr_creation: bool
     * }
     */
    public function validate(
        array $meta,
        ?array $offerSnapshot = null,
        ?array $validatedSnapshot = null,
        ?array $payloadDigest = null,
    ): array {
        $context = SelectedFareContext::fromBookingMeta($meta, $validatedSnapshot ?? $offerSnapshot);
        $selected = $context->selectedFare;
        $mismatches = [];
        $admin = [];

        if ($selected === null) {
            return $this->result(true, [], []);
        }

        $handoff = is_array($context->supplierBookingContext) ? $context->supplierBookingContext : [];
        $provider = strtolower(trim((string) ($context->supplierProvider ?? '')));

        if ($provider === 'sabre' && $handoff !== []) {
            $this->compareSabreHandoff($selected, $handoff, $mismatches, $admin);
        }

        $this->compareConnectionSticky($context, $meta, $offerSnapshot, $mismatches, $admin);
        $this->compareDistributionChannel($context, $meta, $mismatches, $admin);

        if ($payloadDigest !== null && $payloadDigest !== []) {
            $this->comparePayloadDigest($selected, $payloadDigest, $mismatches, $admin);
        }

        $consistent = $mismatches === [];

        return $this->result($consistent, $mismatches, $admin);
    }

    /**
     * @param  list<string>  $mismatches
     * @param  array<string, mixed>  $admin
     */
    protected function compareSabreHandoff(
        SelectedFareOption $selected,
        array $handoff,
        array &$mismatches,
        array &$admin,
    ): void {
        $ctxBrand = strtoupper(trim((string) ($handoff['selected_brand_code'] ?? $handoff['brand_code'] ?? '')));
        $selBrand = strtoupper(trim((string) ($selected->brandCode ?? '')));

        $ctxFb = $this->firstFareBasis($handoff['fare_basis_codes_by_segment'] ?? []);
        $selFb = $this->firstFareBasis($selected->fareBasisCodesBySegment);

        $ctxBaggage = trim((string) ($handoff['baggage'] ?? data_get($handoff, 'selected_fare_family_option.baggage') ?? ''));
        $selBaggage = trim((string) ($selected->baggageSummary ?? ''));

        $ctxTotal = is_numeric($handoff['selected_price_total'] ?? null) ? (float) $handoff['selected_price_total'] : null;

        $admin['selected_brand_code'] = $selBrand !== '' ? $selBrand : null;
        $admin['booking_context_brand_code'] = $ctxBrand !== '' ? $ctxBrand : null;
        $admin['selected_fare_basis'] = $selFb !== '' ? $selFb : null;
        $admin['booking_context_fare_basis'] = $ctxFb !== '' ? $ctxFb : null;
        $admin['selected_baggage'] = $selBaggage !== '' ? $selBaggage : null;
        $admin['booking_context_baggage'] = $ctxBaggage !== '' ? $ctxBaggage : null;
        $admin['selected_total'] = $selected->selectedPriceTotal;
        $admin['booking_context_total'] = $ctxTotal;

        if ($selBrand !== '' && $ctxBrand !== '' && $selBrand !== $ctxBrand) {
            $mismatches[] = 'brand_code';
        }

        if ($selFb !== '' && $ctxFb !== '' && $selFb !== $ctxFb) {
            $mismatches[] = 'fare_basis';
        }

        if ($selBaggage !== '' && $ctxBaggage !== '' && $this->normalizeBaggage($selBaggage) !== $this->normalizeBaggage($ctxBaggage)) {
            $mismatches[] = 'baggage';
        }

        $ctxFbList = $this->stringList($handoff['fare_basis_codes_by_segment'] ?? []);
        $selFbList = $selected->fareBasisCodesBySegment;
        if ($selFbList !== [] && $ctxFbList !== [] && $selFbList !== $ctxFbList) {
            $mismatches[] = 'fare_basis_codes_by_segment';
        }

        $ctxRbd = $this->stringList($handoff['booking_classes_by_segment'] ?? []);
        $selRbd = $selected->bookingClassesBySegment;
        if ($selRbd !== [] && $ctxRbd !== [] && $selRbd !== $ctxRbd) {
            $mismatches[] = 'booking_classes_by_segment';
        }
    }

    /**
     * @param  list<string>  $mismatches
     * @param  array<string, mixed>  $admin
     * @param  array<string, mixed>|null  $offerSnapshot
     */
    protected function compareConnectionSticky(
        SelectedFareContext $context,
        array $meta,
        ?array $offerSnapshot,
        array &$mismatches,
        array &$admin,
    ): void {
        $metaConn = (int) ($meta['supplier_connection_id'] ?? 0);
        $ctxConn = (int) ($context->supplierConnectionId ?? 0);
        $offerConn = is_array($offerSnapshot) ? (int) ($offerSnapshot['supplier_connection_id'] ?? 0) : 0;

        $admin['connection_sticky'] = $metaConn > 0 && ($ctxConn === 0 || $metaConn === $ctxConn);

        if ($metaConn > 0 && $offerConn > 0 && $metaConn !== $offerConn) {
            $mismatches[] = 'supplier_connection_id';
            $admin['connection_sticky'] = false;
        }
    }

    /**
     * @param  list<string>  $mismatches
     * @param  array<string, mixed>  $admin
     */
    protected function compareDistributionChannel(
        SelectedFareContext $context,
        array $meta,
        array &$mismatches,
        array &$admin,
    ): void {
        $ctxChannel = strtolower(trim((string) ($context->distributionChannel ?? '')));
        $metaChannel = strtolower(trim((string) ($meta['distribution_channel'] ?? '')));

        if ($ctxChannel !== '' && $metaChannel !== '' && $ctxChannel !== $metaChannel) {
            $mismatches[] = 'distribution_channel';
        }
    }

    /**
     * @param  array<string, mixed>  $digest
     * @param  list<string>  $mismatches
     * @param  array<string, mixed>  $admin
     */
    protected function comparePayloadDigest(
        SelectedFareOption $selected,
        array $digest,
        array &$mismatches,
        array &$admin,
    ): void {
        $digestBrand = strtoupper(trim((string) ($digest['brand_code'] ?? '')));
        $selBrand = strtoupper(trim((string) ($selected->brandCode ?? '')));
        if ($selBrand !== '' && $digestBrand !== '' && $selBrand !== $digestBrand) {
            $mismatches[] = 'payload_brand_code';
        }

        $digestFb = $this->firstFareBasis($digest['fare_basis_codes_by_segment'] ?? $digest['fare_basis_codes'] ?? []);
        $selFb = $this->firstFareBasis($selected->fareBasisCodesBySegment);
        if ($selFb !== '' && $digestFb !== '' && $selFb !== $digestFb) {
            $mismatches[] = 'payload_fare_basis';
        }

        $admin['pnr_payload_segments_complete'] = ($digest['pnr_payload_segments_complete'] ?? null);
        $admin['blank_segment_rows_present'] = ($digest['blank_segment_rows_present'] ?? null);
        $admin['fare_basis_present'] = ($digest['fare_basis_present'] ?? null);
        $admin['validating_carrier_present'] = ($digest['validating_carrier_present'] ?? null);
    }

    /**
     * @param  list<string>  $mismatches
     * @param  array<string, mixed>  $admin
     * @return array{
     *     consistent: bool,
     *     reason_code: string|null,
     *     mismatch_fields: list<string>,
     *     admin_summary: array<string, mixed>,
     *     blocks_pnr_creation: bool
     * }
     */
    protected function result(bool $consistent, array $mismatches, array $admin): array
    {
        return [
            'consistent' => $consistent,
            'reason_code' => $consistent ? null : self::REASON_MISMATCH,
            'mismatch_fields' => array_values(array_unique($mismatches)),
            'admin_summary' => $admin,
            'blocks_pnr_creation' => ! $consistent,
        ];
    }

    protected function firstFareBasis(mixed $value): string
    {
        $list = $this->stringList($value);

        return $list[0] ?? '';
    }

    /**
     * @return list<string>
     */
    protected function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($v): string => strtoupper(trim((string) $v)),
            $value
        ), static fn (string $s): bool => $s !== ''));
    }

    protected function normalizeBaggage(string $value): string
    {
        return strtolower(preg_replace('/\s+/', '', $value) ?? $value);
    }
}
