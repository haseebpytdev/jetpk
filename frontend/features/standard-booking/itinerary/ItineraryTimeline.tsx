import type { SelectedFlightSummary } from "../types";

type ItineraryTimelineProps = {
  itinerary: SelectedFlightSummary;
};

function SegmentList({ segments, title }: { segments: Array<Record<string, unknown>>; title: string }) {
  if (!segments.length) return null;

  return (
    <div className="mt-4">
      <h3 className="text-jp-sm font-semibold text-jp-text">{title}</h3>
      <ol className="mt-2 space-y-3" aria-label={title}>
        {segments.map((segment, index) => (
          <li key={index} className="rounded-jp-md border border-jp-border p-3 text-jp-sm">
            <p className="font-semibold text-jp-text">
              {(segment.origin as string) ?? "—"} → {(segment.destination as string) ?? "—"}
            </p>
            <p className="text-jp-muted">
              {(segment.departure_time as string) ?? (segment.depart as string) ?? "—"}
              {" · "}
              {(segment.arrival_time as string) ?? (segment.arrive as string) ?? "—"}
            </p>
            <p className="text-jp-muted">
              {(segment.airline_name as string) ?? (segment.carrier as string) ?? ""}
              {(segment.flight_number as string) ? ` ${segment.flight_number as string}` : ""}
            </p>
            {(segment.duration as string) ? <p className="text-jp-muted">Duration: {segment.duration as string}</p> : null}
            {(segment.layover as string) ? <p className="text-jp-muted">Layover: {segment.layover as string}</p> : null}
          </li>
        ))}
      </ol>
    </div>
  );
}

export function ItineraryTimeline({ itinerary }: ItineraryTimelineProps) {
  const outbound = Array.isArray(itinerary.segments) ? itinerary.segments : [];
  const inbound = Array.isArray(itinerary.return_segments) ? itinerary.return_segments : [];

  return (
    <article className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid="itinerary-timeline" id="itinerary">
      <h2 className="text-jp-base font-semibold text-jp-text">Itinerary</h2>
      <p className="mt-1 text-jp-sm text-jp-muted">
        {itinerary.route_label ?? `${itinerary.origin} → ${itinerary.destination}`}
      </p>
      <dl className="mt-3 grid gap-2 text-jp-sm sm:grid-cols-2">
        <div>
          <dt className="text-jp-muted">Trip type</dt>
          <dd className="capitalize">{(itinerary.trip_type ?? "one_way").replace(/_/g, " ")}</dd>
        </div>
        {itinerary.cabin ? (
          <div>
            <dt className="text-jp-muted">Cabin</dt>
            <dd className="capitalize">{itinerary.cabin}</dd>
          </div>
        ) : null}
        {itinerary.fare_family ? (
          <div>
            <dt className="text-jp-muted">Fare family</dt>
            <dd>{itinerary.fare_family}</dd>
          </div>
        ) : null}
        {itinerary.baggage ? (
          <div data-testid="baggage-info">
            <dt className="text-jp-muted">Baggage</dt>
            <dd>{itinerary.baggage}</dd>
          </div>
        ) : null}
      </dl>
      <SegmentList segments={outbound} title="Outbound" />
      <SegmentList segments={inbound} title="Return" />
      {outbound.length === 0 && inbound.length === 0 ? (
        <p className="mt-3 text-jp-sm text-jp-muted">Itinerary details will appear when available from your booking.</p>
      ) : null}
    </article>
  );
}
