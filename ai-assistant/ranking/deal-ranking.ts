/**
 * Deterministic deal ranking for AI explanations.
 * The LLM must not invent “best deal”; it consumes these labels.
 */
export type RankedOfferInput = {
  id: string;
  price: number;
  durationMinutes: number;
  stops: number;
  layoverMinutes?: number | null;
};

export type RankedOffer = RankedOfferInput & {
  labels: Array<"CHEAPEST" | "FASTEST" | "DIRECT" | "SHORTEST_LAYOVER" | "BEST_VALUE">;
  bestValueScore: number;
};

/**
 * Best value heuristic (lower is better):
 * score = price_norm * 0.55 + duration_norm * 0.30 + stops_norm * 0.15
 * where each *_norm is value / max(value in set).
 */
export function rankOffers(offers: RankedOfferInput[]): RankedOffer[] {
  if (offers.length === 0) return [];
  const maxPrice = Math.max(...offers.map((o) => o.price), 1);
  const maxDuration = Math.max(...offers.map((o) => o.durationMinutes), 1);
  const maxStops = Math.max(...offers.map((o) => o.stops), 1);

  const scored = offers.map((o) => {
    const bestValueScore =
      (o.price / maxPrice) * 0.55 +
      (o.durationMinutes / maxDuration) * 0.3 +
      (o.stops / maxStops) * 0.15;
    return { ...o, bestValueScore, labels: [] as RankedOffer["labels"] };
  });

  const cheapestId = [...scored].sort((a, b) => a.price - b.price)[0]?.id;
  const fastestId = [...scored].sort((a, b) => a.durationMinutes - b.durationMinutes)[0]?.id;
  const bestValueId = [...scored].sort((a, b) => a.bestValueScore - b.bestValueScore)[0]?.id;
  const shortestLayoverId = [...scored]
    .filter((o) => (o.layoverMinutes ?? null) !== null)
    .sort((a, b) => (a.layoverMinutes ?? 0) - (b.layoverMinutes ?? 0))[0]?.id;

  return scored.map((o) => {
    const labels: RankedOffer["labels"] = [];
    if (o.id === cheapestId) labels.push("CHEAPEST");
    if (o.id === fastestId) labels.push("FASTEST");
    if (o.stops === 0) labels.push("DIRECT");
    if (o.id === shortestLayoverId) labels.push("SHORTEST_LAYOVER");
    if (o.id === bestValueId) labels.push("BEST_VALUE");
    return { ...o, labels };
  });
}
