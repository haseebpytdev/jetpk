import type { ReturnComboDetails } from "../types";

type ReturnJourneyDetailsProps = {
  returnCombo?: ReturnComboDetails | null;
};

export function ReturnJourneyDetails({ returnCombo }: ReturnJourneyDetailsProps) {
  if (!returnCombo) return null;

  const outbound = returnCombo.outbound_journey as Record<string, string> | null | undefined;
  const returnLeg = returnCombo.return_journey as Record<string, string> | null | undefined;

  return (
    <section data-testid="return-journey-details" aria-labelledby="return-journey-heading">
      <h3 id="return-journey-heading" className="text-sm font-semibold text-jp-text">
        Return itinerary
      </h3>
      <div className="mt-2 space-y-3">
        {outbound ? (
          <div className="rounded-jp-md border border-jp-border p-3">
            <p className="text-xs font-medium uppercase tracking-wide text-jp-text-muted">Outbound (selected)</p>
            <p className="text-sm text-jp-text">
              {outbound.departure_time_display ?? "—"} → {outbound.arrival_time_display ?? "—"}
            </p>
          </div>
        ) : null}
        {returnLeg ? (
          <div className="rounded-jp-md border border-jp-border p-3">
            <p className="text-xs font-medium uppercase tracking-wide text-jp-text-muted">Return</p>
            <p className="text-sm text-jp-text">
              {returnLeg.departure_time_display ?? "—"} → {returnLeg.arrival_time_display ?? "—"}
            </p>
          </div>
        ) : null}
        {returnCombo.total_display ? (
          <p className="text-sm font-medium text-jp-text">Combo total: {returnCombo.total_display}</p>
        ) : null}
      </div>
    </section>
  );
}
