import type { SelectedFlightSummary } from "../types";

type ItineraryTimelineProps = {
  itinerary: SelectedFlightSummary;
};

function text(segment: Record<string, unknown>, keys: string[]): string | null {
  for (const key of keys) {
    const value = segment[key];
    if (typeof value === "string" && value.trim()) return value.trim();
    if (typeof value === "number" && Number.isFinite(value)) return String(value);
  }
  return null;
}

function SegmentCard({ segment, index }: { segment: Record<string, unknown>; index: number }) {
  const origin = text(segment, ["origin_airport_code", "origin"]) ?? "—";
  const destination = text(segment, ["destination_airport_code", "destination"]) ?? "—";
  const originCity = text(segment, ["origin_city", "origin_city_name"]);
  const destinationCity = text(segment, ["destination_city", "destination_city_name"]);
  const departTime = text(segment, ["departure_time_display", "departure_time", "depart"]);
  const arriveTime = text(segment, ["arrival_time_display", "arrival_time", "arrive"]);
  const departDate = text(segment, ["departure_date_display", "departure_date", "depart_date"]);
  const arriveDate = text(segment, ["arrival_date_display", "arrival_date", "arrive_date"]);
  const airline = text(segment, ["airline_name", "marketing_carrier_name", "carrier"]);
  const airlineCode = text(segment, ["airline_code", "marketing_carrier", "carrier_code"]);
  const flightNumber = text(segment, ["flight_number", "marketing_flight_number"]);
  const duration = text(segment, ["duration_display", "duration"]);
  const layover = text(segment, ["layover_display", "layover", "connection_time"]);
  const terminal = text(segment, ["departure_terminal", "origin_terminal", "terminal"]);
  const aircraft = text(segment, ["aircraft", "aircraft_name", "equipment"]);
  const cabin = text(segment, ["cabin", "cabin_name"]);
  const operating = text(segment, ["operating_carrier_name", "operating_airline"]);
  const logo = text(segment, ["airline_logo_url", "logo_url"]);

  return (
    <li className="rounded-jp-md border border-jp-border-soft bg-jp-page p-3" data-testid="review-segment-card">
      <div className="flex items-center gap-3">
        <div className="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-jp-md border border-jp-border bg-white">
          {logo ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={logo} alt="" className="h-7 w-7 object-contain" />
          ) : (
            <span className="text-[10px] font-bold text-jp-primary">{(airlineCode ?? "JP").slice(0, 2)}</span>
          )}
        </div>
        <div className="min-w-0">
          <p className="truncate text-sm font-semibold text-jp-text">
            {airline ?? airlineCode ?? "Airline"}
            {flightNumber ? ` · ${flightNumber}` : ""}
          </p>
          <p className="text-xs text-jp-muted">Segment {index + 1}</p>
        </div>
      </div>

      <div className="mt-3 grid grid-cols-[1fr_auto_1fr] items-start gap-2">
        <div>
          <p className="text-base font-bold tabular-nums text-jp-text">{departTime ?? "—"}</p>
          <p className="text-sm font-semibold text-jp-text">{origin}</p>
          {originCity ? <p className="text-xs text-jp-muted">{originCity}</p> : null}
          {departDate ? <p className="mt-0.5 text-[11px] text-jp-muted">{departDate}</p> : null}
        </div>
        <div className="pt-2 text-center text-[10px] uppercase tracking-wide text-jp-muted">
          {duration ?? "Flight"}
        </div>
        <div className="text-right">
          <p className="text-base font-bold tabular-nums text-jp-text">{arriveTime ?? "—"}</p>
          <p className="text-sm font-semibold text-jp-text">{destination}</p>
          {destinationCity ? <p className="text-xs text-jp-muted">{destinationCity}</p> : null}
          {arriveDate ? <p className="mt-0.5 text-[11px] text-jp-muted">{arriveDate}</p> : null}
        </div>
      </div>

      <dl className="mt-3 grid gap-1 text-xs text-jp-muted sm:grid-cols-2">
        {terminal ? (
          <div>
            <dt className="inline text-jp-muted">Terminal: </dt>
            <dd className="inline text-jp-text">{terminal}</dd>
          </div>
        ) : null}
        {aircraft ? (
          <div>
            <dt className="inline text-jp-muted">Aircraft: </dt>
            <dd className="inline text-jp-text">{aircraft}</dd>
          </div>
        ) : null}
        {cabin ? (
          <div>
            <dt className="inline text-jp-muted">Cabin: </dt>
            <dd className="inline capitalize text-jp-text">{cabin}</dd>
          </div>
        ) : null}
        {operating ? (
          <div>
            <dt className="inline text-jp-muted">Operated by: </dt>
            <dd className="inline text-jp-text">{operating}</dd>
          </div>
        ) : null}
      </dl>

      {layover ? (
        <p className="mt-3 rounded-jp-md bg-jp-surface-muted px-3 py-2 text-xs font-medium text-jp-text">
          Layover {layover}
        </p>
      ) : null}
    </li>
  );
}

function SegmentList({ segments, title }: { segments: Array<Record<string, unknown>>; title: string }) {
  if (!segments.length) return null;

  return (
    <div className="mt-4">
      <h3 className="text-jp-sm font-semibold uppercase tracking-wide text-jp-primary">{title}</h3>
      <ol className="mt-2 space-y-3" aria-label={title}>
        {segments.map((segment, index) => (
          <SegmentCard key={index} segment={segment} index={index} />
        ))}
      </ol>
    </div>
  );
}

function FareDetails({ itinerary }: { itinerary: SelectedFlightSummary }) {
  const rows: Array<[string, string | null | undefined]> = [
    ["Selected fare", itinerary.fare_family],
    ["Cabin baggage", itinerary.cabin_baggage],
    ["Checked baggage", itinerary.checked_baggage ?? itinerary.baggage],
    ["Meal", itinerary.meal],
    ["Cancellation / refund", itinerary.refund_rule],
    ["Modification / change", itinerary.change_rule],
  ];

  const visible = rows.filter(([, value]) => typeof value === "string" && value.trim());
  if (!visible.length) return null;

  return (
    <div className="mt-4 rounded-jp-md border border-jp-border-soft bg-jp-page p-3" data-testid="review-selected-fare">
      <h3 className="text-jp-sm font-semibold text-jp-text">Selected fare details</h3>
      <dl className="mt-2 space-y-1.5 text-jp-sm">
        {visible.map(([label, value]) => (
          <div key={label} className="flex justify-between gap-3">
            <dt className="text-jp-muted">{label}</dt>
            <dd className="text-right font-medium text-jp-text">{value}</dd>
          </div>
        ))}
      </dl>
    </div>
  );
}

export function ItineraryTimeline({ itinerary }: ItineraryTimelineProps) {
  const outbound = Array.isArray(itinerary.segments) ? itinerary.segments : [];
  const inbound = Array.isArray(itinerary.return_segments) ? itinerary.return_segments : [];
  const isReturn = (itinerary.trip_type ?? "").includes("round") || inbound.length > 0;

  return (
    <article className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid="itinerary-timeline" id="itinerary">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-jp-base font-semibold text-jp-text">Itinerary</h2>
          <p className="mt-1 text-jp-sm text-jp-muted">
            {itinerary.route_label ?? `${itinerary.origin} → ${itinerary.destination}`}
          </p>
        </div>
        <div className="flex flex-wrap gap-1.5 text-xs text-jp-muted">
          {itinerary.duration ? (
            <span className="rounded-jp-pill bg-jp-page px-2 py-1">{itinerary.duration}</span>
          ) : null}
          {itinerary.stops != null ? (
            <span className="rounded-jp-pill bg-jp-page px-2 py-1">
              {itinerary.stops === 0 ? "Direct" : `${itinerary.stops} Stop${itinerary.stops === 1 ? "" : "s"}`}
            </span>
          ) : null}
          {itinerary.cabin ? (
            <span className="rounded-jp-pill bg-jp-page px-2 py-1 capitalize">{itinerary.cabin}</span>
          ) : null}
        </div>
      </div>

      <SegmentList segments={outbound} title={isReturn ? "Outbound" : "Flight"} />
      <SegmentList segments={inbound} title="Return" />
      {outbound.length === 0 && inbound.length === 0 ? (
        <p className="mt-3 text-jp-sm text-jp-muted">Itinerary details will appear when available from your booking.</p>
      ) : null}
      <FareDetails itinerary={itinerary} />
    </article>
  );
}
