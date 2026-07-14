<?php

namespace App\Support\Pricing;

use Illuminate\Support\Facades\Log;

/**
 * RETURN-SPLIT-SELECT-R4 — public customer pricing policy.
 *
 * Public channels accept only admin_markup toward final_total; route/airline/agent/service_fee
 * buckets are rejected from customer-facing totals while preserved in diagnostic fields.
 */
class PublicCustomerPricing
{
    /** @var list<string> */
    private const PUBLIC_CHANNELS = ['public_guest', 'public_search', 'public_web'];

    /** @var list<string> */
    private const REJECTED_BUCKETS = [
        'route_markup',
        'airline_markup',
        'agent_markup_or_commission',
        'service_fee',
    ];

    public static function isPublicChannel(string $channel): bool
    {
        return in_array(strtolower(trim($channel)), self::PUBLIC_CHANNELS, true);
    }

    /**
     * @param  array<string, mixed>  $components
     * @param  array<string, mixed>  $context  search_id, offer_id, source_channel
     * @return array<string, mixed>
     */
    public static function sanitizeComponents(array $components, array $context = []): array
    {
        $rawFinal = (float) ($components['final_total'] ?? 0);
        $supplierTotal = (float) ($components['supplier_total'] ?? 0);
        if ($supplierTotal <= 0) {
            $supplierTotal = round(
                (float) ($components['base_fare'] ?? 0) + (float) ($components['taxes'] ?? 0),
                2,
            );
        }

        $adminMarkup = (float) ($components['admin_markup'] ?? 0);
        $rejectedByBucket = [];
        $rejectedTotal = 0.0;
        $rejectedRules = [];

        foreach (self::REJECTED_BUCKETS as $bucket) {
            $amount = (float) ($components[$bucket] ?? 0);
            if ($amount > 0) {
                $rejectedByBucket[$bucket] = round($amount, 2);
                $rejectedTotal += $amount;
            }
            $components[$bucket] = 0.0;
        }

        $appliedRules = is_array($components['applied_rules'] ?? null) ? $components['applied_rules'] : [];
        $adminRules = [];
        foreach ($appliedRules as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $bucket = strtolower((string) ($rule['bucket'] ?? ''));
            if ($bucket === 'admin_markup') {
                $adminRules[] = $rule;

                continue;
            }
            if ($bucket !== '' && in_array($bucket, self::REJECTED_BUCKETS, true)) {
                $rejectedRules[] = $rule;
            }
        }

        $publicFinal = round($supplierTotal + $adminMarkup, 2);

        $components['applied_rules'] = $adminRules;
        $components['public_pricing_raw_final_total'] = round($rawFinal, 2);
        $components['public_pricing_rejected_markup'] = round($rejectedTotal, 2);
        $components['public_pricing_rejected_by_bucket'] = $rejectedByBucket;
        $components['public_pricing_rejected_rules'] = $rejectedRules;
        $components['public_pricing_sanitized'] = true;
        $components['final_total'] = $publicFinal;

        self::logSanitization($components, $context, $rejectedByBucket, $rejectedRules, $rawFinal, $publicFinal);

        return $components;
    }

    /**
     * @param  array<string, mixed>  $components
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function sanitizeIfPublicChannel(array $components, string $sourceChannel, array $context = []): array
    {
        if (! self::isPublicChannel($sourceChannel)) {
            return $components;
        }

        $context['source_channel'] = $sourceChannel;

        return self::sanitizeComponents($components, $context);
    }

    /**
     * @param  array<string, mixed>  $components
     * @param  array<string, mixed>  $context
     * @param  array<string, float>  $rejectedByBucket
     * @param  list<array<string, mixed>>  $rejectedRules
     */
    protected static function logSanitization(
        array $components,
        array $context,
        array $rejectedByBucket,
        array $rejectedRules,
        float $rawFinal,
        float $publicFinal,
    ): void {
        $searchId = isset($context['search_id']) ? (string) $context['search_id'] : null;
        $offerId = isset($context['offer_id']) ? (string) $context['offer_id'] : null;
        $appliedRules = is_array($components['applied_rules'] ?? null) ? $components['applied_rules'] : [];

        $tracePayload = [
            'search_id' => $searchId,
            'offer_id' => $offerId,
            'source_channel' => (string) ($context['source_channel'] ?? ''),
            'base_fare' => (float) ($components['base_fare'] ?? 0),
            'taxes' => (float) ($components['taxes'] ?? 0),
            'supplier_total' => (float) ($components['supplier_total'] ?? 0),
            'admin_markup' => (float) ($components['admin_markup'] ?? 0),
            'accepted_admin_markup' => (float) ($components['admin_markup'] ?? 0),
            'final_total' => $publicFinal,
            'applied_rule_ids' => array_values(array_filter(array_map(
                static fn (array $rule): ?int => isset($rule['id']) ? (int) $rule['id'] : null,
                $appliedRules,
            ))),
            'applied_rule_names' => array_values(array_filter(array_map(
                static fn (array $rule): string => (string) ($rule['name'] ?? ''),
                $appliedRules,
            ))),
            'applied_rule_buckets' => array_values(array_filter(array_map(
                static fn (array $rule): string => (string) ($rule['bucket'] ?? ''),
                $appliedRules,
            ))),
        ];

        try {
            Log::info('public_pricing_markup_source_trace', $tracePayload);
        } catch (\Throwable) {
            /* non-critical */
        }

        if ($rejectedTotal = (float) ($components['public_pricing_rejected_markup'] ?? 0)) {
            try {
                Log::info('public_pricing_markup_rejected', [
                    'search_id' => $searchId,
                    'offer_id' => $offerId,
                    'rejected_markup_total' => $rejectedTotal,
                    'rejected_by_bucket' => $rejectedByBucket,
                    'rejected_rule_ids' => array_values(array_filter(array_map(
                        static fn (array $rule): ?int => isset($rule['id']) ? (int) $rule['id'] : null,
                        $rejectedRules,
                    ))),
                    'rejected_rule_names' => array_values(array_filter(array_map(
                        static fn (array $rule): string => (string) ($rule['name'] ?? ''),
                        $rejectedRules,
                    ))),
                    'rejected_rule_types' => array_values(array_filter(array_map(
                        static fn (array $rule): string => (string) ($rule['rule_type'] ?? ''),
                        $rejectedRules,
                    ))),
                ]);
            } catch (\Throwable) {
                /* non-critical */
            }
        }

        if (abs($rawFinal - $publicFinal) > 0.009) {
            try {
                Log::info('public_pricing_total_reconciled', [
                    'search_id' => $searchId,
                    'offer_id' => $offerId,
                    'raw_final_total' => round($rawFinal, 2),
                    'public_final_total' => round($publicFinal, 2),
                    'reconciliation_delta' => round($rawFinal - $publicFinal, 2),
                    'accepted_admin_markup' => (float) ($components['admin_markup'] ?? 0),
                ]);
            } catch (\Throwable) {
                /* non-critical */
            }
        }
    }
}
