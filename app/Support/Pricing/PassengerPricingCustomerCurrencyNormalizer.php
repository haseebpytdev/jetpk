<?php

namespace App\Support\Pricing;

/**
 * Convert authoritative supplier PTC rows into customer display currency using the
 * same FX rate already applied to offer pricing_components — never invent splits.
 */
class PassengerPricingCustomerCurrencyNormalizer
{
    private const TOLERANCE = 2.0;

    /**
     * @param  list<array<string, mixed>>|null  $rows
     * @param  array<string, mixed>  $pricingComponents
     * @return array{
     *     passenger_pricing: list<array<string, mixed>>|null,
     *     passenger_pricing_available: bool,
     *     components_trusted: bool
     * }
     */
    public static function normalize(?array $rows, array $pricingComponents, ?float $authoritativeCustomerSupplierTotal = null): array
    {
        if (! is_array($rows) || $rows === []) {
            return [
                'passenger_pricing' => null,
                'passenger_pricing_available' => false,
                'components_trusted' => false,
            ];
        }

        $targetCurrency = strtoupper(trim((string) ($pricingComponents['pricing_currency'] ?? 'PKR')));
        if ($targetCurrency === '') {
            $targetCurrency = 'PKR';
        }
        $conversionStatus = (string) ($pricingComponents['conversion_status'] ?? 'same_currency');
        $fxRate = (float) ($pricingComponents['fx_rate'] ?? 0);

        $normalized = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $mapped = self::normalizeRow($row, $targetCurrency, $conversionStatus, $fxRate);
            if ($mapped === null) {
                continue;
            }
            $normalized[] = $mapped;
        }

        if ($normalized === []) {
            return [
                'passenger_pricing' => null,
                'passenger_pricing_available' => false,
                'components_trusted' => false,
            ];
        }

        $rowSum = round(array_sum(array_map(
            static fn (array $row): float => (float) ($row['total_amount'] ?? 0),
            $normalized
        )), 2);

        $supplierTotal = $authoritativeCustomerSupplierTotal;
        if ($supplierTotal === null || $supplierTotal <= 0) {
            $supplierTotal = (float) ($pricingComponents['supplier_total'] ?? 0);
        }

        if ($supplierTotal > 0 && abs($rowSum - round($supplierTotal, 2)) > max(self::TOLERANCE, $supplierTotal * 0.02)) {
            // Do not invent a split that fails reconciliation with the priced supplier total.
            return [
                'passenger_pricing' => null,
                'passenger_pricing_available' => false,
                'components_trusted' => false,
            ];
        }

        $componentsTrusted = true;
        foreach ($normalized as $idx => $row) {
            $base = (float) ($row['base_amount'] ?? 0);
            $tax = (float) ($row['tax_amount'] ?? 0);
            $total = (float) ($row['total_amount'] ?? 0);
            if ($base > 0 && abs(($base + $tax) - $total) > max(self::TOLERANCE, $total * 0.02)) {
                $normalized[$idx]['base_amount'] = null;
                $normalized[$idx]['tax_amount'] = null;
                $componentsTrusted = false;
            }
        }

        return [
            'passenger_pricing' => array_values(array_map(static function (array $row): array {
                return array_filter($row, static fn (mixed $v): bool => $v !== null && $v !== '');
            }, $normalized)),
            'passenger_pricing_available' => true,
            'components_trusted' => $componentsTrusted,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    protected static function normalizeRow(array $row, string $targetCurrency, string $conversionStatus, float $fxRate): ?array
    {
        $total = (float) ($row['total_amount'] ?? $row['total'] ?? 0);
        if ($total <= 0) {
            return null;
        }

        $rowCurrency = strtoupper(trim((string) ($row['currency'] ?? '')));
        if ($rowCurrency === '') {
            $rowCurrency = $targetCurrency;
        }

        $base = (float) ($row['base_amount'] ?? $row['base_fare'] ?? 0);
        $tax = (float) ($row['tax_amount'] ?? $row['taxes'] ?? 0);
        $quantity = max(1, (int) ($row['passenger_count'] ?? $row['quantity'] ?? 1));
        $type = strtolower(trim((string) ($row['passenger_type'] ?? $row['ptc'] ?? 'adult')));
        if ($type === 'adults') {
            $type = 'adult';
        } elseif ($type === 'children') {
            $type = 'child';
        } elseif ($type === 'infants') {
            $type = 'infant';
        }

        if ($rowCurrency !== $targetCurrency) {
            if ($conversionStatus !== 'converted' || $fxRate <= 0) {
                return null;
            }
            $total = round($total * $fxRate, 2);
            $base = $base > 0 ? round($base * $fxRate, 2) : 0.0;
            $tax = $tax > 0 ? round($tax * $fxRate, 2) : 0.0;
            $rowCurrency = $targetCurrency;
        }

        return [
            'supplier_passenger_id' => isset($row['supplier_passenger_id'])
                ? substr(trim((string) $row['supplier_passenger_id']), 0, 64)
                : null,
            'passenger_type' => $type !== '' ? $type : 'adult',
            'passenger_count' => $quantity,
            'ptc' => isset($row['ptc']) ? strtoupper(trim((string) $row['ptc'])) : null,
            'base_amount' => $base > 0 ? $base : null,
            'tax_amount' => $tax > 0 ? $tax : null,
            'total_amount' => $total,
            'currency' => $rowCurrency,
        ];
    }
}
