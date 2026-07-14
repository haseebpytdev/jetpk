<?php

namespace App\Services\Pricing;

use App\Enums\MarkupRuleStatus;
use App\Enums\MarkupRuleType;
use App\Enums\MarkupValueType;
use App\Models\Agency;
use App\Models\MarkupRule;
use App\Services\Fx\LiveFxRateService;
use App\Support\Pricing\IatiFarePricingResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Agency markup rules + FX conversion on supplier fare inputs.
 * When `supplier_total` is present on the input fare array, it is the authoritative supplier-quoted total (e.g. Sabre `totalFare.totalPrice`), superseding `base_fare` + `taxes` for pricing and percentage rules. Non-ISO currency tag `PK` is normalized to `PKR` before FX lookup.
 */
class PricingRuleService
{
    public function __construct(
        protected FlightPricingService $defaultPricing,
        protected LiveFxRateService $fxRateService,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return Collection<int, MarkupRule>
     */
    public function getApplicableRules(Agency $agency, array $context): Collection
    {
        $now = now();

        $rules = MarkupRule::query()
            ->where('agency_id', $agency->id)
            ->where('status', MarkupRuleStatus::Active->value)
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        return $rules->filter(function (MarkupRule $rule) use ($context): bool {
            try {
                return $this->matchesRule($rule, $context);
            } catch (\Throwable $e) {
                Log::warning('Skipped invalid markup rule while matching context.', [
                    'rule_id' => $rule->id,
                    'agency_id' => $rule->agency_id,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        })->values();
    }

    /**
     * @param  array<string, mixed>  $supplierFare
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function calculateMarkup(Agency $agency, array $supplierFare, array $context): array
    {
        $supplierKey = strtolower(trim((string) ($context['supplier'] ?? '')));
        if ($supplierKey === 'iati') {
            $supplierFare = IatiFarePricingResolver::supplierFareFromBreakdown($supplierFare);
        }

        $priced = $this->defaultPricing->applyToOffers([$supplierFare])[0];
        $rules = $this->getApplicableRules($agency, $context);
        $supplierCurrency = $this->normalizeSupplierCurrencyForFx(
            strtoupper(trim((string) ($supplierFare['currency'] ?? $priced['currency'] ?? 'PKR')))
        );
        $sourceBaseFare = (float) ($priced['base_fare'] ?? 0);
        $sourceTaxes = (float) ($priced['taxes'] ?? 0);
        $explicitSupplierTotal = (float) ($supplierFare['supplier_total'] ?? 0);
        $usedExplicitSupplierTotal = $explicitSupplierTotal > 0;
        $sourceSupplierTotal = $usedExplicitSupplierTotal
            ? $explicitSupplierTotal
            : ($sourceBaseFare + $sourceTaxes);
        $fxMeta = $this->fxRateService->getRate($supplierCurrency, 'PKR');
        $conversionStatus = (string) ($fxMeta['status'] ?? 'conversion_missing');
        $fxRate = (float) ($fxMeta['rate'] ?? 0);
        $pricingCurrency = $supplierCurrency;

        $baseFare = $sourceBaseFare;
        $taxes = $sourceTaxes;
        $supplierTotal = $sourceSupplierTotal;
        if ($conversionStatus === 'converted' && $fxRate > 0) {
            $baseFare = round($sourceBaseFare * $fxRate, 2);
            $taxes = round($sourceTaxes * $fxRate, 2);
            $supplierTotal = $usedExplicitSupplierTotal
                ? round($sourceSupplierTotal * $fxRate, 2)
                : round($baseFare + $taxes, 2);
            $pricingCurrency = 'PKR';
        } elseif ($conversionStatus === 'same_currency') {
            $pricingCurrency = $supplierCurrency;
        }

        $components = [
            'base_fare' => $baseFare,
            'taxes' => $taxes,
            'supplier_total' => $supplierTotal,
            'supplier_total_source' => $sourceSupplierTotal,
            'supplier_currency' => $supplierCurrency,
            'pricing_currency' => $pricingCurrency,
            'conversion_status' => $conversionStatus,
            'fx_rate' => $fxRate > 0 ? $fxRate : null,
            'fx_fetched_at' => $fxMeta['fetched_at'] ?? null,
            'admin_markup' => 0.0,
            'route_markup' => 0.0,
            'airline_markup' => 0.0,
            'agent_markup_or_commission' => 0.0,
            'service_fee' => 0.0,
            'final_total' => 0.0,
        ];

        $applied = [];

        if ($rules->isEmpty()) {
            $components['final_total'] = $supplierTotal;

            $result = $components + [
                'applied_rules' => [],
            ];

            if ($supplierKey === 'iati') {
                $result = IatiFarePricingResolver::guardPricingComponents($supplierFare, $result);
            }

            return $result;
        }

        foreach ($rules as $rule) {
            try {
                $amount = $this->ruleAmount($rule, $supplierTotal);
            } catch (\Throwable $e) {
                Log::warning('Skipped invalid markup rule while pricing.', [
                    'rule_id' => $rule->id,
                    'agency_id' => $rule->agency_id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
            if ($amount <= 0) {
                continue;
            }

            try {
                $bucket = $this->resolveBucket($rule);
            } catch (\Throwable $e) {
                Log::warning('Skipped invalid markup rule bucket while pricing.', [
                    'rule_id' => $rule->id,
                    'agency_id' => $rule->agency_id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
            $components[$bucket] += $amount;

            $applied[] = [
                'id' => $rule->id,
                'name' => $rule->name,
                'rule_type' => $rule->rule_type->value,
                'value' => (float) $rule->value,
                'value_type' => $rule->value_type->value,
                'amount' => round($amount, 2),
                'bucket' => $bucket,
            ];
        }

        $components['final_total'] = $supplierTotal
            + $components['admin_markup']
            + $components['route_markup']
            + $components['airline_markup']
            + $components['agent_markup_or_commission']
            + $components['service_fee'];

        $result = $components + [
            'applied_rules' => $applied,
        ];

        if ($supplierKey === 'iati') {
            $result = IatiFarePricingResolver::guardPricingComponents($supplierFare, $result);
        }

        return $result;
    }

    /**
     * Normalize supplier-reported currency codes before FX lookup (Sabre sometimes emits non-ISO "PK" for PKR).
     */
    protected function normalizeSupplierCurrencyForFx(string $currency): string
    {
        return match ($currency) {
            'PK' => 'PKR',
            default => $currency,
        };
    }

    /**
     * @param  array<string, mixed>  $fare
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function applyRules(Agency $agency, array $fare, array $context): array
    {
        return $this->calculateMarkup($agency, $fare, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function matchesRule(MarkupRule $rule, array $context): bool
    {
        $appliesTo = $rule->applies_to ?? [];
        if (! is_array($appliesTo)) {
            return false;
        }

        return match ($rule->rule_type) {
            MarkupRuleType::Global => true,
            MarkupRuleType::Route => $this->valueInContext($appliesTo, 'route', $context['route'] ?? null),
            MarkupRuleType::Airline => $this->valueInContext($appliesTo, 'airline', $context['airline'] ?? null),
            MarkupRuleType::Supplier => $this->valueInContext($appliesTo, 'supplier', $context['supplier'] ?? null),
            MarkupRuleType::Agent => $this->matchAgentRule($appliesTo, $context),
            MarkupRuleType::Cabin => $this->valueInContext($appliesTo, 'cabin', $context['cabin'] ?? null),
            MarkupRuleType::FareFamily => $this->valueInContext($appliesTo, 'fare_family', $context['fare_family'] ?? null),
        };
    }

    /**
     * @param  array<string, mixed>  $appliesTo
     * @param  array<string, mixed>  $context
     */
    protected function matchAgentRule(array $appliesTo, array $context): bool
    {
        $sourceChannel = strtolower((string) ($context['source_channel'] ?? ''));
        if (isset($appliesTo['source_channel']) && strtolower((string) $appliesTo['source_channel']) !== $sourceChannel) {
            return false;
        }

        if (isset($appliesTo['agent_id'])) {
            return (int) $appliesTo['agent_id'] === (int) ($context['agent_id'] ?? 0);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $appliesTo
     */
    protected function valueInContext(array $appliesTo, string $key, mixed $contextValue): bool
    {
        if (! isset($appliesTo[$key]) || $contextValue === null || $contextValue === '') {
            return false;
        }

        $expected = strtolower((string) $appliesTo[$key]);
        $actual = strtolower((string) $contextValue);

        return $expected === $actual;
    }

    protected function ruleAmount(MarkupRule $rule, float $supplierTotal): float
    {
        $value = (float) ($rule->value ?? 0);

        if ($rule->value_type === MarkupValueType::Percentage) {
            return round(($supplierTotal * $value) / 100, 2);
        }

        return round($value, 2);
    }

    protected function resolveBucket(MarkupRule $rule): string
    {
        $bucket = strtolower((string) ($rule->meta['bucket'] ?? ''));
        if (in_array($bucket, ['admin_markup', 'route_markup', 'airline_markup', 'agent_markup_or_commission', 'service_fee'], true)) {
            return $bucket;
        }

        return match ($rule->rule_type) {
            MarkupRuleType::Route => 'route_markup',
            MarkupRuleType::Airline => 'airline_markup',
            MarkupRuleType::Agent => 'agent_markup_or_commission',
            default => 'admin_markup',
        };
    }
}
