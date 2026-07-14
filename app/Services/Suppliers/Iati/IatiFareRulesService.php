<?php

namespace App\Services\Suppliers\Iati;

/**
 * IATI embeds fare rules in fare/search payloads — no separate fare-rules endpoint in reference.
 */
class IatiFareRulesService
{
    /**
     * @param  array<string, mixed>  $fareData
     * @return list<string>
     */
    public function extractFromFareData(array $fareData): array
    {
        $rules = is_array($fareData['change_rules'] ?? null) ? $fareData['change_rules'] : [];

        return $this->normalizeRules($rules);
    }

    /**
     * @param  array<string, mixed>  $fare
     * @return list<string>
     */
    public function extractFromFare(array $fare): array
    {
        $rules = is_array($fare['change_rules'] ?? null) ? $fare['change_rules'] : [];

        return $this->normalizeRules($rules);
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     * @return list<string>
     */
    public function normalizeRules(array $rules): array
    {
        $labels = [];
        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $type = strtoupper(trim((string) ($rule['type'] ?? 'CHANGE')));
            $before = trim((string) ($rule['before_departure_status'] ?? ''));
            $after = trim((string) ($rule['after_departure_status'] ?? ''));
            if ($before !== '') {
                $labels[] = $type.' before departure: '.$before;
            }
            if ($after !== '') {
                $labels[] = $type.' after departure: '.$after;
            }
        }

        return array_values(array_unique($labels));
    }
}
