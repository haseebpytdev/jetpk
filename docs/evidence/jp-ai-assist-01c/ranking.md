# Deterministic ranking

PHP `DealRankingService` ports `ai-assistant/ranking/deal-ranking.ts`.

BEST_VALUE (lower better):

`score = price_norm * 0.55 + duration_norm * 0.30 + stops_norm * 0.15`

Labels: CHEAPEST, FASTEST, DIRECT, SHORTEST_LAYOVER, BEST_VALUE.

Top recommendations: 2–4 with label diversity. Model may explain labels; must not invent authoritative totals.
