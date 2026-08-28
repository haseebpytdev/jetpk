import { AirlineIdentity } from "@/features/flight-results/components/AirlineIdentity";
import { TimeRouteBlock } from "@/features/flight-results/components/TimeRouteBlock";
import type { FlightSegmentDisplay } from "@/features/flight-results/types";
import type { LayoverDisplay } from "../types";

type SegmentDetailsProps = {
  segments: FlightSegmentDisplay[];
  layovers?: LayoverDisplay[];
  airlineLogoUrl?: string | null;
  /** When true, gaps are connection layovers only (single journey). */
  journeyBoundaryIndexes?: number[];
};

function airportCode(segment: FlightSegmentDisplay, side: "origin" | "destination"): string | undefined {
  if (side === "origin") return segment.origin_airport_code ?? segment.origin;
  return segment.destination_airport_code ?? segment.destination;
}

function formatDurationMinutes(totalMinutes: number): string {
  const mins = Math.max(0, Math.round(totalMinutes));
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  if (h <= 0) return `${m}m`;
  if (m <= 0) return `${h}h`;
  return `${h}h ${m}m`;
}

function parseFlexibleDate(value?: string | null): Date | null {
  const raw = value?.trim();
  if (!raw) return null;
  const parsed = new Date(raw);
  if (!Number.isNaN(parsed.getTime())) return parsed;
  return null;
}

function deriveLayoverFromSegments(
  previous: FlightSegmentDisplay,
  next: FlightSegmentDisplay,
): LayoverDisplay | undefined {
  const arrival =
    parseFlexibleDate((previous as FlightSegmentDisplay & { arrival_at?: string }).arrival_at)
    ?? parseFlexibleDate(previous.arrival_date_display && previous.arrival_time_display
      ? `${previous.arrival_date_display} ${previous.arrival_time_display}`
      : null);
  const departure =
    parseFlexibleDate((next as FlightSegmentDisplay & { departure_at?: string }).departure_at)
    ?? parseFlexibleDate(next.departure_date_display && next.departure_time_display
      ? `${next.departure_date_display} ${next.departure_time_display}`
      : null);

  if (!arrival || !departure) return undefined;
  const minutes = (departure.getTime() - arrival.getTime()) / 60000;
  if (!Number.isFinite(minutes) || minutes < 0 || minutes > 60 * 48) return undefined;

  const airport = airportCode(previous, "destination") ?? airportCode(next, "origin");
  return {
    airport_code: airport,
    duration_display: formatDurationMinutes(minutes),
    duration_minutes: Math.round(minutes),
    overnight: minutes >= 8 * 60 || arrival.getUTCDate() !== departure.getUTCDate(),
    kind: "connection",
  };
}

function parseLayoverFromSegment(segment: FlightSegmentDisplay): LayoverDisplay | undefined {
  const text = segment.layover_after_display?.trim();
  if (!text) return undefined;
  const match = text.match(/^(.+?)\s+layover\s+in\s+([A-Z]{3})/i);
  return {
    duration_display: match?.[1]?.trim() ?? text,
    airport_code: match?.[2],
    kind: "connection",
  };
}

function enrichLayover(layover: LayoverDisplay): LayoverDisplay {
  if (layover.duration_display?.trim()) return layover;
  if (typeof layover.duration_minutes === "number" && Number.isFinite(layover.duration_minutes)) {
    return { ...layover, duration_display: formatDurationMinutes(layover.duration_minutes) };
  }
  return layover;
}

function detectDestinationStay(
  previous: FlightSegmentDisplay,
  next: FlightSegmentDisplay,
): boolean {
  const prevDest = (airportCode(previous, "destination") ?? "").toUpperCase();
  const nextOrigin = (airportCode(next, "origin") ?? "").toUpperCase();
  const prevOrigin = (airportCode(previous, "origin") ?? "").toUpperCase();
  const nextDest = (airportCode(next, "destination") ?? "").toUpperCase();
  // Round-trip turnaround: arrive at destination, later depart back toward origin.
  if (prevDest && nextOrigin && prevDest === nextOrigin && prevOrigin && nextDest && prevOrigin === nextDest) {
    return true;
  }
  return false;
}

function LayoverBlock({ layover }: { layover?: LayoverDisplay }) {
  if (!layover) return null;
  const isStay = layover.kind === "destination_stay";
  const place = isStay
    ? (layover.airport_code
      ? `Time in ${layover.airport_code}`
      : layover.city
        ? `Stay at destination · ${layover.city}`
        : "Stay at destination")
    : layover.airport_code
      ? `Layover in ${layover.airport_code}`
      : layover.city
        ? `Layover in ${layover.city}`
        : "Layover";
  const duration = layover.duration_display?.trim();

  return (
    <div
      className={
        isStay
          ? "my-3 rounded-jp-md border border-jp-border bg-jp-page px-4 py-3 text-center"
          : "my-3 rounded-jp-md border border-dashed border-jp-border bg-jp-surface-muted/70 px-4 py-3 text-center"
      }
      data-testid={isStay ? "destination-stay-block" : "layover-block"}
      data-layover-kind={layover.kind ?? "connection"}
      tabIndex={0}
      role="note"
      aria-label={[place, duration].filter(Boolean).join(" · ")}
    >
      <p className="text-sm font-medium text-jp-text">{place}</p>
      {duration ? <p className="mt-0.5 text-sm text-jp-text-muted">{duration}</p> : null}
      {!isStay && layover.terminal_change ? <p className="mt-1 text-xs text-jp-text-muted">Terminal change</p> : null}
      {!isStay && layover.overnight ? <p className="mt-1 text-xs text-jp-text-muted">Overnight layover</p> : null}
    </div>
  );
}

export function SegmentDetails({
  segments,
  layovers = [],
  airlineLogoUrl,
  journeyBoundaryIndexes = [],
}: SegmentDetailsProps) {
  if (segments.length === 0) {
    return <p className="text-sm text-jp-text-muted">Segment details are not available for this fare.</p>;
  }

  const boundary = new Set(journeyBoundaryIndexes);

  return (
    <div data-testid="route-timeline" role="list" aria-label="Journey details">
      <ol className="space-y-4" data-testid="segment-details">
        {segments.map((segment, index) => {
          const legNumber = segment.segment_number ?? index + 1;
          const origin = airportCode(segment, "origin");
          const destination = airportCode(segment, "destination");
          const originCity = segment.origin_city?.trim();
          const destCity = segment.destination_city?.trim();
          const originLabel = originCity && origin ? `${originCity} (${origin})` : origin ?? "—";
          const destLabel = destCity && destination ? `${destCity} (${destination})` : destination ?? "—";
          const airlineName = segment.airline_name ?? undefined;
          const airlineCode = segment.airline_code ?? segment.marketing_carrier_code ?? undefined;
          const cabin = segment.cabin_display ?? segment.cabin ?? undefined;
          const flightMeta = [segment.flight_number, cabin].filter(Boolean).join(" · ");

          let gap: LayoverDisplay | undefined;
          if (index < segments.length - 1) {
            const next = segments[index + 1];
            const isBoundary = boundary.has(index) || detectDestinationStay(segment, next);
            if (isBoundary) {
              const derived = deriveLayoverFromSegments(segment, next);
              gap = enrichLayover({
                ...(derived ?? {}),
                airport_code: airportCode(segment, "destination") ?? derived?.airport_code,
                kind: "destination_stay",
                duration_display: derived?.duration_display,
                duration_minutes: derived?.duration_minutes,
              });
            } else {
              const stops = Number((segment as FlightSegmentDisplay & { stops?: number }).stops ?? 0);
              // Direct single-leg journeys never show connection blocks between phantom gaps.
              const raw = layovers[index] ?? parseLayoverFromSegment(segment) ?? deriveLayoverFromSegments(segment, next);
              gap = raw ? enrichLayover({ ...raw, kind: raw.kind ?? "connection" }) : undefined;
              if (segments.length === 1 || (stops === 0 && segments.length === 2 && !raw && !gap)) {
                // keep gap as computed
              }
            }
          }

          return (
            <li key={`${segment.flight_number ?? "seg"}-${index}`} className="list-none">
              <article className="space-y-3" data-testid="journey-leg">
                <header className="flex flex-wrap items-start justify-between gap-2">
                  <div>
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-jp-primary">
                      Leg {legNumber}
                    </p>
                    <p className="mt-0.5 text-sm font-semibold text-jp-text">
                      {originLabel} <span className="text-jp-text-muted">→</span> {destLabel}
                    </p>
                  </div>
                  {segment.departure_date_display ? (
                    <p className="text-xs font-medium text-jp-text-muted">{segment.departure_date_display}</p>
                  ) : null}
                </header>

                <div className="flex flex-wrap items-center justify-between gap-3">
                  <AirlineIdentity
                    code={airlineCode}
                    name={airlineName}
                    logoUrl={segment.airline_logo_url ?? airlineLogoUrl}
                    size="sm"
                  />
                  <div className="min-w-0 text-right text-xs text-jp-text-muted">
                    {flightMeta ? <p className="font-medium text-jp-text">{flightMeta}</p> : null}
                    {segment.aircraft_display ? <p className="mt-0.5">{segment.aircraft_display}</p> : null}
                    {segment.operating_airline_name || segment.operating_airline_code || segment.operating_carrier_code ? (
                      <p className="mt-0.5">
                        Operated by{" "}
                        {segment.operating_airline_name ??
                          segment.operating_airline_code ??
                          segment.operating_carrier_code}
                      </p>
                    ) : null}
                  </div>
                </div>

                <TimeRouteBlock
                  departureTime={segment.departure_time_display}
                  arrivalTime={segment.arrival_time_display}
                  arrivalDayOffset={segment.arrival_day_offset_display ?? segment.arrival_day_offset}
                  originCode={origin}
                  destinationCode={destination}
                  duration={segment.duration_display}
                  hideStops
                  className="rounded-jp-md bg-jp-page/60 px-2 py-2 sm:px-3"
                />

                {(originCity || destCity || segment.terminal_departure || segment.terminal_arrival || segment.arrival_date_display) ? (
                  <div className="grid gap-2 text-xs text-jp-text-muted sm:grid-cols-2">
                    <div>
                      {originCity ? <p>{originCity}</p> : null}
                      {segment.terminal_departure ? <p>Terminal {segment.terminal_departure}</p> : null}
                      {segment.departure_date_display ? <p>{segment.departure_date_display}</p> : null}
                    </div>
                    <div className="sm:text-right">
                      {destCity ? <p>{destCity}</p> : null}
                      {segment.terminal_arrival ? <p>Terminal {segment.terminal_arrival}</p> : null}
                      {segment.arrival_date_display ? <p>{segment.arrival_date_display}</p> : null}
                    </div>
                  </div>
                ) : null}
              </article>

              {gap ? <LayoverBlock layover={gap} /> : null}
            </li>
          );
        })}
      </ol>
    </div>
  );
}
