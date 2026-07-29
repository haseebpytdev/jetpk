import type { FlightSegmentDisplay } from "../types";
import { TimeRouteBlock } from "./TimeRouteBlock";

type FlightSegmentSummaryProps = {
  segments?: FlightSegmentDisplay[];
};

export function FlightSegmentSummary({ segments }: FlightSegmentSummaryProps) {
  if (!segments?.length) return null;

  const first = segments[0];
  const last = segments[segments.length - 1];

  return (
    <div className="space-y-2">
      <TimeRouteBlock
        departureTime={first.departure_time_display}
        arrivalTime={last.arrival_time_display}
        arrivalDayOffset={last.arrival_day_offset_display}
        originCode={first.origin_airport_code ?? first.origin}
        destinationCode={last.destination_airport_code ?? last.destination}
        duration={segments.length === 1 ? first.duration_display : undefined}
      />
      {segments.length > 1 ? (
        <p className="text-xs text-jp-text-muted">
          {segments.length} segment{segments.length === 1 ? "" : "s"}
          {segments.map((segment) => segment.flight_number).filter(Boolean).length
            ? ` · ${segments.map((segment) => segment.flight_number).filter(Boolean).join(", ")}`
            : ""}
        </p>
      ) : first.flight_number ? (
        <p className="text-xs text-jp-text-muted">Flight {first.flight_number}</p>
      ) : null}
    </div>
  );
}
