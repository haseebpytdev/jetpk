import { AirlineIdentity } from "@/features/flight-results/components/AirlineIdentity";
import { TimeRouteBlock } from "@/features/flight-results/components/TimeRouteBlock";
import type { FlightSegmentDisplay } from "@/features/flight-results/types";
import type { LayoverDisplay } from "../types";

type SegmentDetailsProps = {
  segments: FlightSegmentDisplay[];
  layovers?: LayoverDisplay[];
  airlineLogoUrl?: string | null;
};

function airportCode(segment: FlightSegmentDisplay, side: "origin" | "destination"): string | undefined {
  if (side === "origin") return segment.origin_airport_code ?? segment.origin;
  return segment.destination_airport_code ?? segment.destination;
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
  const place = layover.airport_code
    ? `Layover in ${layover.airport_code}`
    : layover.city
      ? `Layover in ${layover.city}`
      : "Layover";
  const duration = layover.duration_display?.trim();

  return (
    <div
      className="my-3 rounded-jp-md border border-dashed border-jp-border bg-jp-surface-muted/70 px-4 py-3 text-center"
      data-testid="layover-block"
      tabIndex={0}
      role="note"
      aria-label={[place, duration].filter(Boolean).join(" · ")}
    >
      <p className="text-sm font-medium text-jp-text">{place}</p>
      {duration ? <p className="mt-0.5 text-sm text-jp-text-muted">{duration}</p> : null}
      {layover.terminal_change ? <p className="mt-1 text-xs text-jp-text-muted">Terminal change</p> : null}
      {layover.overnight ? <p className="mt-1 text-xs text-jp-text-muted">Overnight layover</p> : null}
    </div>
  );
}

export function SegmentDetails({ segments, layovers = [], airlineLogoUrl }: SegmentDetailsProps) {
  if (segments.length === 0) {
    return <p className="text-sm text-jp-text-muted">Segment details are not available for this fare.</p>;
  }

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
          const layover =
            index < segments.length - 1
              ? (layovers[index] ?? parseLayoverFromSegment(segment))
              : undefined;

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
                  stops={0}
                  stopsLabel="Direct"
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

              {layover ? <LayoverBlock layover={layover} /> : null}
            </li>
          );
        })}
      </ol>
    </div>
  );
}
