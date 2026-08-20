import type { FlightSegmentDisplay } from "@/features/flight-results/types";

type SegmentDetailsProps = {
  segments: FlightSegmentDisplay[];
};

export function SegmentDetails({ segments }: SegmentDetailsProps) {
  if (segments.length === 0) {
    return <p className="text-sm text-jp-text-muted">Segment details are not available for this fare.</p>;
  }

  return (
    <ol className="space-y-3" data-testid="segment-details">
      {segments.map((segment, index) => (
        <li
          key={`${segment.flight_number ?? "seg"}-${index}`}
          className="rounded-jp-md border border-jp-border bg-jp-page p-3"
        >
          <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="font-medium text-jp-text">
              {segment.airline_name ?? segment.airline_code ?? segment.marketing_carrier_code ?? "Airline not supplied"} · {segment.flight_number ?? "Flight number not supplied"}
            </p>
            {segment.duration_display ? (
              <span className="text-xs text-jp-text-muted">{segment.duration_display}</span>
            ) : null}
          </div>
          <div className="mt-2 grid gap-2 text-sm sm:grid-cols-2">
            <div>
              <p className="text-jp-text-muted">Departure</p>
              <p className="font-medium text-jp-text">
                {segment.departure_date_display ? `${segment.departure_date_display} · ` : ""}{segment.departure_time_display ?? "Not supplied"}
                {segment.origin_airport_code || segment.origin ? (
                  <span className="text-jp-text-muted">
                    {" "}
                    · {segment.origin_airport_code ?? segment.origin}
                  </span>
                ) : null}
              </p>
            </div>
            <div>
              <p className="text-jp-text-muted">Arrival</p>
              <p className="font-medium text-jp-text">
                {segment.arrival_time_display ?? "—"}
                {segment.arrival_day_offset_display ? (
                  <span className="text-xs text-jp-text-muted"> {segment.arrival_day_offset_display}</span>
                ) : null}
                {segment.destination_airport_code || segment.destination ? (
                  <span className="text-jp-text-muted">
                    {" "}
                    · {segment.destination_airport_code ?? segment.destination}
                  </span>
                ) : null}
                {segment.arrival_date_display ? <span className="block text-xs font-normal text-jp-text-muted">{segment.arrival_date_display}</span> : null}
              </p>
            </div>
          </div>
          <dl className="mt-2 grid gap-1 text-xs text-jp-text-muted sm:grid-cols-2">
            {segment.operating_airline_name || segment.operating_airline_code || segment.operating_carrier_code ? (
              <div>
                <dt className="inline">Operating carrier: </dt>
                <dd className="inline text-jp-text">{segment.operating_airline_name ?? segment.operating_airline_code ?? segment.operating_carrier_code}</dd>
              </div>
            ) : null}
            {segment.cabin_display || segment.cabin || segment.booking_class ? (
              <div>
                <dt className="inline">Cabin / class: </dt>
                <dd className="inline text-jp-text">{[segment.cabin_display ?? segment.cabin, segment.booking_class].filter(Boolean).join(" · ")}</dd>
              </div>
            ) : null}
            {segment.aircraft_display ? <div><dt className="inline">Aircraft: </dt><dd className="inline text-jp-text">{segment.aircraft_display}</dd></div> : null}
            {segment.terminal_departure || segment.terminal_arrival ? <div><dt className="inline">Terminal: </dt><dd className="inline text-jp-text">{segment.terminal_departure ? `Depart ${segment.terminal_departure}` : "Departure not supplied"}{segment.terminal_arrival ? ` · Arrive ${segment.terminal_arrival}` : ""}</dd></div> : null}
          </dl>
        </li>
      ))}
    </ol>
  );
}
