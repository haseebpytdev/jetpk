import type { LayoverDisplay } from "../types";
import type { FlightSegmentDisplay } from "@/features/flight-results/types";

type RouteTimelineProps = {
  segments: FlightSegmentDisplay[];
  layovers?: LayoverDisplay[];
};

export function RouteTimeline({ segments, layovers = [] }: RouteTimelineProps) {
  if (segments.length === 0) return null;

  return (
    <div className="space-y-0" data-testid="route-timeline" role="list" aria-label="Itinerary timeline">
      {segments.map((segment, index) => (
        <div key={`timeline-${index}`} role="listitem">
          <div className="relative border-l-2 border-jp-primary/30 pl-4">
            <span className="absolute -left-[5px] top-2 h-2 w-2 rounded-full bg-jp-primary" aria-hidden />
            <p className="text-sm font-medium text-jp-text">
              {segment.departure_time_display ?? "—"} → {segment.arrival_time_display ?? "—"}
            </p>
            <p className="text-xs text-jp-text-muted">
              {segment.origin_airport_code ?? segment.origin ?? "—"} to{" "}
              {segment.destination_airport_code ?? segment.destination ?? "—"}
              {segment.flight_number ? ` · ${segment.flight_number}` : ""}
            </p>
          </div>
          {index < segments.length - 1 ? (
            <LayoverBlock
              layover={
                layovers[index] ??
                parseLayoverFromSegment(segment)
              }
            />
          ) : null}
        </div>
      ))}
    </div>
  );
}

function parseLayoverFromSegment(segment: FlightSegmentDisplay): LayoverDisplay | undefined {
  const text = segment.layover_after_display?.trim();
  if (!text) return undefined;
  const match = text.match(/^(.+?)\s+layover\s+in\s+([A-Z]{3})/i);
  return {
    duration_display: match?.[1]?.trim() ?? text,
    airport_code: match?.[2],
  };
}

function LayoverBlock({ layover }: { layover?: LayoverDisplay }) {
  if (!layover) return null;
  const label = [
    layover.duration_display,
    layover.airport_code ? `layover in ${layover.airport_code}` : null,
    layover.city && !layover.airport_code ? layover.city : null,
  ]
    .filter(Boolean)
    .join(" ");

  return (
    <div
      className="my-3 rounded-jp-md bg-jp-muted/60 px-4 py-3 text-center text-sm text-jp-text"
      data-testid="layover-block"
      tabIndex={0}
      role="note"
      aria-label={label || "Layover"}
    >
      <p>{label || "Layover"}</p>
      {layover.terminal_change ? (
        <p className="mt-1 text-xs text-jp-text-muted">Terminal change</p>
      ) : null}
      {layover.overnight ? <p className="mt-1 text-xs text-jp-text-muted">Overnight layover</p> : null}
    </div>
  );
}
