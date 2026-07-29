import type { FlightOffer } from "../types";

type BaggageSummaryProps = {
  offer: Pick<FlightOffer, "baggage" | "refund_rule" | "change_rule" | "cabin" | "fare_family">;
};

export function BaggageSummary({ offer }: BaggageSummaryProps) {
  const items = [
    offer.baggage ? { label: "Baggage", value: offer.baggage } : null,
    offer.refund_rule ? { label: "Refund", value: offer.refund_rule } : null,
    offer.change_rule ? { label: "Changes", value: offer.change_rule } : null,
    offer.cabin ? { label: "Cabin", value: offer.cabin } : null,
    offer.fare_family ? { label: "Fare family", value: offer.fare_family } : null,
  ].filter(Boolean) as Array<{ label: string; value: string }>;

  if (!items.length) return null;

  return (
    <ul className="flex flex-wrap gap-x-3 gap-y-1 text-xs text-jp-text-muted" aria-label="Fare details">
      {items.map((item) => (
        <li key={item.label}>
          <span className="sr-only">{item.label}: </span>
          {item.value}
        </li>
      ))}
    </ul>
  );
}
