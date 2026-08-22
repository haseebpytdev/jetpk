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

function componentsReconcile(
  base: number | null | undefined,
  taxes: number | null | undefined,
  serviceFee: number | null | undefined,
  total: number | null | undefined,
): boolean {
  if (base == null || base <= 0 || total == null || total <= 0) return false;
  const sum = base + (taxes ?? 0) + (serviceFee && serviceFee > 0 ? serviceFee : 0);
  return Math.abs(sum - total) <= Math.max(2, total * 0.02);
}

function passengerTypeLabel(raw: string): string {
  const key = raw.trim().toUpperCase();
  if (key === "ADULT" || key === "ADT" || key === "ADULTS") return "ADULT";
  if (key === "CHILD" || key === "CHD" || key === "CNN" || key === "CHILDREN") return "CHILD";
  if (key === "INFANT" || key === "INF" || key === "INS" || key === "INFANTS") return "INFANT";
  return key || "PASSENGER";
}

export function PriceBreakdown({ offer, breakdown }: PriceBreakdownProps) {
  const currency = String(breakdown?.currency ?? breakdown?.displayed_currency ?? "PKR").toUpperCase();
  // Offer-level selected total is authoritative; breakdown may lag or reflect a prior fare.
  const total =
    offer.displayed_price ??
    offer.final_customer_price ??
    breakdown?.displayed_price ??
    breakdown?.grand_total;

  const base = breakdown?.base_fare ?? offer.base_fare;
  const taxes = breakdown?.taxes ?? offer.taxes;
  const serviceFee = breakdown?.service_fee ?? offer.service_fee;
  const explicitUnavailable = breakdown?.component_breakdown_unavailable === true
    || breakdown?.component_breakdown_available === false;
  const showComponents =
    currency === "PKR"
    && !explicitUnavailable
    && componentsReconcile(base, taxes, serviceFee, total);

  const rows = showComponents
    ? [
        { label: "Base fare", value: formatPkr(base) },
        { label: "Taxes & fees", value: formatPkr(taxes) },
        { label: "Service fee", value: formatPkr(serviceFee) },
      ].filter((row) => row.value)
    : [];

  const passengerRows = (breakdown?.passenger_pricing ?? []).map((row) => {
    const value = row as Record<string, unknown>;
    const rowCurrency = String(value.currency ?? "PKR").toUpperCase();
    if (rowCurrency !== "PKR") return null;
    const rowTotal = Number(value.total_amount ?? value.total ?? 0);
    if (rowTotal <= 0) return null;
    const quantity = Math.max(1, Number(value.passenger_count ?? value.quantity ?? 1));
    const rowBase = Number(value.base_amount ?? value.base_fare ?? 0);
    const rowTaxes = Number(value.tax_amount ?? value.taxes ?? 0);
    const rowShowsParts = rowBase > 0 && Math.abs(rowBase + rowTaxes - rowTotal) <= Math.max(2, rowTotal * 0.02);
    return {
      type: passengerTypeLabel(String(value.passenger_type ?? value.ptc ?? "Passenger")),
      quantity,
      fare: rowShowsParts ? formatPkr(rowBase) : null,
      taxes: rowShowsParts ? formatPkr(rowTaxes) : null,
      total: formatPkr(rowTotal),
    };
  }).filter((row): row is NonNullable<typeof row> => row !== null && row.total !== null);

  return (
    <section data-testid="price-breakdown" aria-labelledby="price-breakdown-heading">
      <h3 id="price-breakdown-heading" className="text-sm font-semibold text-jp-text">
        Fare Details
      </h3>
      <dl className="mt-2.5 space-y-2 text-sm">
        {passengerRows.length ? (
          <div className="overflow-x-auto rounded-jp-md border border-jp-border" data-testid="passenger-fare-breakdown">
            <table className="w-full min-w-[36rem] text-left text-xs">
              <thead className="bg-jp-surface-muted text-[10px] uppercase tracking-wide text-jp-text-muted">
                <tr>
                  <th className="px-3 py-2 font-semibold">Passenger</th>
                  <th className="px-3 py-2 text-right font-semibold">Qty</th>
                  <th className="px-3 py-2 text-right font-semibold">Fare</th>
                  <th className="px-3 py-2 text-right font-semibold">Taxes &amp; fees</th>
                  <th className="px-3 py-2 text-right font-semibold">Total</th>
                </tr>
              </thead>
              <tbody>
                {passengerRows.map((row, index) => (
                  <tr key={`${row.type}-${index}`} className="border-t border-jp-border-soft">
                    <td className="px-3 py-2 font-medium">{row.type}</td>
                    <td className="px-3 py-2 text-right tabular-nums">{row.quantity}</td>
                    <td className="px-3 py-2 text-right">{row.fare ?? "—"}</td>
                    <td className="px-3 py-2 text-right">{row.taxes ?? "—"}</td>
                    <td className="px-3 py-2 text-right font-semibold">{row.total}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : null}
        {rows.map((row) => (
          <div key={row.label} className="flex flex-wrap items-center justify-between gap-2">
            <dt className="text-jp-text-muted">{row.label}</dt>
            <dd className="font-medium text-jp-text">{row.value}</dd>
          </div>
        ))}
        {!showComponents && !passengerRows.length ? (
          <p className="text-xs text-jp-text-muted" data-testid="fare-component-unavailable">
            Component fare breakdown is unavailable for this fare. Grand total is the authoritative customer price.
          </p>
        ) : null}
        <div className="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-jp-md bg-jp-primary/5 px-3 py-3">
          <div>
            <dt className="text-[11px] font-semibold uppercase tracking-wide text-jp-text-muted">Grand total</dt>
            <p className="text-[10px] uppercase tracking-wide text-jp-text-muted">Inclusive of all taxes &amp; fees</p>
          </div>
          <dd className="text-xl font-bold text-jp-primary">
            {formatPkr(total) ?? "Fare unavailable"}
          </dd>
        </div>
      </dl>
      {offer.price_note || breakdown?.price_note ? (
        <p className="mt-2 text-xs text-jp-text-muted">{breakdown?.price_note ?? offer.price_note}</p>
      ) : null}
    </section>
  );
}
