<?php

namespace App\Services\Ai;

/**
 * Deterministic deal ranking — PHP port of ai-assistant/ranking/deal-ranking.ts
 *
 * BEST_VALUE (lower better):
 * score = price_norm * 0.55 + duration_norm * 0.30 + stops_norm * 0.15
 */
final class DealRankingService
{
    /**
     * @param  list<array{id: string, price: float|int, duration_minutes: int, stops: int, layover_minutes?: int|null}>  $offers
     * @return list<array{id: string, price: float, duration_minutes: int, stops: int, layover_minutes: int|null, best_value_score: float, labels: list<string>}>
     */
    public function rank(array $offers): array
    {
        if ($offers === []) {
            return [];
        }

        $maxPrice = max(1.0, ...array_map(static fn ($o) => (float) $o['price'], $offers));
        $maxDuration = max(1, ...array_map(static fn ($o) => (int) $o['duration_minutes'], $offers));
        $maxStops = max(1, ...array_map(static fn ($o) => (int) $o['stops'], $offers));

        $scored = [];
        foreach ($offers as $o) {
            $price = (float) $o['price'];
            $duration = (int) $o['duration_minutes'];
            $stops = (int) $o['stops'];
            $layover = array_key_exists('layover_minutes', $o) && $o['layover_minutes'] !== null
                ? (int) $o['layover_minutes']
                : null;
            $bestValue = ($price / $maxPrice) * 0.55
                + ($duration / $maxDuration) * 0.30
                + ($stops / $maxStops) * 0.15;
            $scored[] = [
                'id' => (string) $o['id'],
                'price' => $price,
                'duration_minutes' => $duration,
                'stops' => $stops,
                'layover_minutes' => $layover,
                'best_value_score' => $bestValue,
                'labels' => [],
            ];
        }

        $cheapestId = collect($scored)->sortBy('price')->first()['id'] ?? null;
        $fastestId = collect($scored)->sortBy('duration_minutes')->first()['id'] ?? null;
        $bestValueId = collect($scored)->sortBy('best_value_score')->first()['id'] ?? null;
        $shortestLayoverId = collect($scored)
            ->filter(static fn ($o) => $o['layover_minutes'] !== null)
            ->sortBy('layover_minutes')
            ->first()['id'] ?? null;

        foreach ($scored as &$row) {
            $labels = [];
            if ($row['id'] === $cheapestId) {
                $labels[] = 'CHEAPEST';
            }
            if ($row['id'] === $fastestId) {
                $labels[] = 'FASTEST';
            }
            if ($row['stops'] === 0) {
                $labels[] = 'DIRECT';
            }
            if ($row['id'] === $shortestLayoverId) {
                $labels[] = 'SHORTEST_LAYOVER';
            }
            if ($row['id'] === $bestValueId) {
                $labels[] = 'BEST_VALUE';
            }
            $row['labels'] = $labels;
        }
        unset($row);

        return $scored;
    }

    /**
     * Pick 2–4 useful recommendations preferring labeled variety.
     *
     * @param  list<array{id: string, labels: list<string>}>  $ranked
     * @return list<array>
     */
    public function topRecommendations(array $ranked, int $limit = 4): array
    {
        $limit = max(2, min(4, $limit));
        $picked = [];
        $seen = [];
        $priority = ['BEST_MATCH', 'CHEAPEST', 'DIRECT', 'FASTEST', 'BEST_VALUE', 'SHORTEST_LAYOVER'];

        foreach ($priority as $label) {
            foreach ($ranked as $row) {
                if (in_array($label, $row['labels'], true) && ! isset($seen[$row['id']])) {
                    $picked[] = $row;
                    $seen[$row['id']] = true;
                    if (count($picked) >= $limit) {
                        return $picked;
                    }
                }
            }
        }

        foreach ($ranked as $row) {
            if (! isset($seen[$row['id']])) {
                $picked[] = $row;
                $seen[$row['id']] = true;
                if (count($picked) >= $limit) {
                    break;
                }
            }
        }

        return $picked;
    }
}
