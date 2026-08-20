import type { FlightOffer } from "../types";

type BaggageSummaryProps = {
  offer: Pick<FlightOffer, "baggage">;
};

export function BaggageSummary({ offer }: BaggageSummaryProps) {
  if (!offer.baggage) return null;

  return (
    <span className="inline-flex items-center gap-1 text-xs text-jp-text-muted" aria-label={`Baggage: ${offer.baggage}`}>
      <span aria-hidden="true">▣</span>
      {offer.baggage}
    </span>
  );
}
