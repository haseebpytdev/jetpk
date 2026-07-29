import type { PriceBreakdownContract } from "../types";
import type { FlightOffer } from "@/features/flight-results/types";

type PriceBreakdownProps = {
  offer: FlightOffer;
  breakdown?: PriceBreakdownContract | null;
};

function formatPkr(amount: number | null | undefined): string | null {
  if (amount == null || amount <= 0) return null;
  return `${Math.round(amount).toLocaleString("en-PK")} PKR`;
}

export function PriceBreakdown({ offer, breakdown }: PriceBreakdownProps) {
  const base = breakdown?.base_fare ?? offer.base_fare;
  const taxes = breakdown?.taxes ?? offer.taxes;
  const markup = breakdown?.markup ?? offer.markup;
  const serviceFee = breakdown?.service_fee ?? offer.service_fee;
  const total =
    breakdown?.displayed_price ??
    breakdown?.grand_total ??
    offer.displayed_price ??
    offer.final_customer_price;

  const rows = [
    { label: "Base fare", value: formatPkr(base) },
    { label: "Taxes & fees", value: formatPkr(taxes) },
    { label: "Agency markup", value: formatPkr(markup) },
    { label: "Service fee", value: formatPkr(serviceFee) },
  ].filter((row) => row.value);

  return (
    <section data-testid="price-breakdown" aria-labelledby="price-breakdown-heading">
      <h3 id="price-breakdown-heading" className="text-sm font-semibold text-jp-text">
        Price breakdown
      </h3>
      <dl className="mt-2 space-y-2 text-sm">
        {rows.map((row) => (
          <div key={row.label} className="flex flex-wrap items-center justify-between gap-2">
            <dt className="text-jp-text-muted">{row.label}</dt>
            <dd className="font-medium text-jp-text">{row.value}</dd>
          </div>
        ))}
        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-jp-border pt-2">
          <dt className="font-semibold text-jp-text">Total</dt>
          <dd className="text-base font-semibold text-jp-text">
            {offer.price_display ?? formatPkr(total) ?? "Fare unavailable"}
          </dd>
        </div>
      </dl>
      {offer.price_note ? <p className="mt-2 text-xs text-jp-text-muted">{offer.price_note}</p> : null}
    </section>
  );
}
