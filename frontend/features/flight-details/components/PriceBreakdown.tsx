import type { PriceBreakdownContract } from "../types";
import type { FlightOffer } from "@/features/flight-results/types";

type PriceBreakdownProps = {
  offer: FlightOffer;
  breakdown?: PriceBreakdownContract | null;
};

function formatPkr(amount: number | null | undefined): string | null {
  if (amount == null || amount <= 0) return null;
  return `PKR ${Math.round(amount).toLocaleString("en-PK")}`;
}

export function PriceBreakdown({ offer, breakdown }: PriceBreakdownProps) {
  const base = breakdown?.base_fare ?? offer.base_fare;
  const taxes = breakdown?.taxes ?? offer.taxes;
  const serviceFee = breakdown?.service_fee ?? offer.service_fee;
  const total =
    breakdown?.displayed_price ??
    breakdown?.grand_total ??
    offer.displayed_price ??
    offer.final_customer_price;

  const rows = [
    { label: "Base fare", value: formatPkr(base) },
    { label: "Taxes & fees", value: formatPkr(taxes) },
    { label: "Service fee", value: formatPkr(serviceFee) },
  ].filter((row) => row.value);
  const passengerRows = (breakdown?.passenger_pricing ?? []).map((row) => {
    const value = row as Record<string, unknown>;
    const currency = String(value.currency ?? "PKR").toUpperCase();
    if (currency !== "PKR") return null;
    return {
      type: String(value.passenger_type ?? value.ptc ?? "Passenger").toUpperCase(),
      quantity: Math.max(1, Number(value.passenger_count ?? value.quantity ?? 1)),
      base: formatPkr(Number(value.base_amount ?? value.base_fare ?? 0)),
      taxes: formatPkr(Number(value.tax_amount ?? value.taxes ?? 0)),
      total: formatPkr(Number(value.total_amount ?? value.total ?? 0)),
    };
  }).filter((row): row is NonNullable<typeof row> => row !== null && row.total !== null);

  return (
    <section data-testid="price-breakdown" aria-labelledby="price-breakdown-heading">
      <h3 id="price-breakdown-heading" className="text-sm font-semibold text-jp-text">
        Price breakdown
      </h3>
      <dl className="mt-2.5 space-y-2 text-sm">
        {passengerRows.length ? <div className="overflow-x-auto rounded-jp-md border border-jp-border" data-testid="passenger-fare-breakdown"><table className="w-full min-w-[32rem] text-left text-xs"><thead className="bg-jp-surface-muted text-jp-text-muted"><tr><th className="px-3 py-2">Passenger</th><th className="px-3 py-2 text-right">Base price</th><th className="px-3 py-2 text-right">Taxes &amp; fees</th><th className="px-3 py-2 text-right">Total</th></tr></thead><tbody>{passengerRows.map((row, index) => <tr key={`${row.type}-${index}`} className="border-t border-jp-border-soft"><td className="px-3 py-2 font-medium">{row.type}<span className="block font-normal text-jp-text-muted">Qty: {row.quantity}</span></td><td className="px-3 py-2 text-right">{row.base ?? "Not supplied"}</td><td className="px-3 py-2 text-right">{row.taxes ?? "Not supplied"}</td><td className="px-3 py-2 text-right font-semibold">{row.total}</td></tr>)}</tbody></table></div> : null}
        {rows.map((row) => (
          <div key={row.label} className="flex flex-wrap items-center justify-between gap-2">
            <dt className="text-jp-text-muted">{row.label}</dt>
            <dd className="font-medium text-jp-text">{row.value}</dd>
          </div>
        ))}
        <div className="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-jp-md bg-jp-primary/5 px-3 py-3">
          <dt className="font-semibold text-jp-text">Grand total</dt>
          <dd className="text-xl font-bold text-jp-primary">
            {formatPkr(total) ?? "Fare unavailable"}
          </dd>
        </div>
      </dl>
      {offer.price_note ? <p className="mt-2 text-xs text-jp-text-muted">{offer.price_note}</p> : null}
    </section>
  );
}
