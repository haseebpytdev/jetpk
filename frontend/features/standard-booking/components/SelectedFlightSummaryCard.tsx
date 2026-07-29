import type { SelectedFlightSummary } from "../types";

type SelectedFlightSummaryCardProps = {
  itinerary: SelectedFlightSummary;
  travellerTotal: number;
  collapsed?: boolean;
};

export function SelectedFlightSummaryCard({
  itinerary,
  travellerTotal,
  collapsed,
}: SelectedFlightSummaryCardProps) {
  return (
    <aside
      className="rounded-jp-lg border border-jp-border bg-jp-surface p-4"
      data-testid="selected-flight-summary"
    >
      <h2 className="text-jp-sm font-semibold text-jp-text">Selected flight</h2>
      <p className="mt-1 text-jp-sm text-jp-muted">
        {itinerary.route_label ?? `${itinerary.origin} → ${itinerary.destination}`}
      </p>
      {!collapsed ? (
        <>
          <dl className="mt-3 space-y-1 text-jp-sm">
            <div className="flex justify-between gap-2">
              <dt className="text-jp-muted">Airline</dt>
              <dd>{itinerary.airline_name ?? itinerary.airline_code ?? "—"}</dd>
            </div>
            {itinerary.flight_number ? (
              <div className="flex justify-between gap-2">
                <dt className="text-jp-muted">Flight</dt>
                <dd>{itinerary.flight_number}</dd>
              </div>
            ) : null}
            {itinerary.duration ? (
              <div className="flex justify-between gap-2">
                <dt className="text-jp-muted">Duration</dt>
                <dd>{itinerary.duration}</dd>
              </div>
            ) : null}
            {itinerary.baggage ? (
              <div className="flex justify-between gap-2">
                <dt className="text-jp-muted">Baggage</dt>
                <dd>{itinerary.baggage}</dd>
              </div>
            ) : null}
            <div className="flex justify-between gap-2">
              <dt className="text-jp-muted">Passengers</dt>
              <dd>{travellerTotal}</dd>
            </div>
          </dl>
        </>
      ) : null}
      {itinerary.total_formatted ? (
        <p className="mt-3 text-lg font-semibold text-jp-text" data-testid="summary-total">
          {itinerary.currency} {itinerary.total_formatted}
        </p>
      ) : null}
    </aside>
  );
}
