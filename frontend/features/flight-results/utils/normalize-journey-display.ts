/**
 * Normalize Laravel journey_display / FE fixture shapes into one UI contract.
 * Laravel uses origin/destination/stops_count; FE historically expected *_airport_code/stops.
 */
export type NormalizedJourneyDisplay = {
  departure_time_display: string;
  arrival_time_display: string;
  departure_date_display: string;
  arrival_date_display: string;
  duration_display: string;
  origin_airport_code: string;
  destination_airport_code: string;
  stops: number;
  stops_label_display: string;
  layover_summary_display?: string[];
  arrival_day_offset_display?: string;
  airline_code: string;
  airline_name: string;
  airline_logo_url: string | null;
  flight_number: string;
};

type JourneyLike = Record<string, unknown> | null | undefined;

function asString(value: unknown, fallback = ""): string {
  return typeof value === "string" && value.trim() !== "" ? value.trim() : fallback;
}

function asStops(journey: JourneyLike): number {
  if (!journey) return 0;
  if (typeof journey.stops === "number") return journey.stops;
  if (typeof journey.stops_count === "number") return journey.stops_count;
  const parsed = Number(journey.stops ?? journey.stops_count);
  return Number.isFinite(parsed) && parsed >= 0 ? parsed : 0;
}

function asLayoverSummary(journey: JourneyLike): string[] | undefined {
  if (!journey) return undefined;
  const raw = journey.layover_summary_display ?? journey.layover_summary;
  return Array.isArray(raw) ? (raw.filter((item) => typeof item === "string") as string[]) : undefined;
}

function airlineFromSegments(journey: JourneyLike): { code: string; name: string } {
  const segments = Array.isArray(journey?.segments_display) ? journey?.segments_display : [];
  const first = segments[0];
  if (!first || typeof first !== "object") return { code: "", name: "" };
  const row = first as Record<string, unknown>;
  return {
    code: asString(row.airline_code ?? row.marketing_airline_code),
    name: asString(row.airline_name ?? row.marketing_airline_name),
  };
}

export function normalizeJourneyDisplay(
  journey: JourneyLike,
  fallback?: {
    airline_code?: string | null;
    airline_name?: string | null;
    airline_logo_url?: string | null;
  },
): NormalizedJourneyDisplay {
  const fromSeg = airlineFromSegments(journey);
  const origin = asString(journey?.origin_airport_code ?? journey?.origin, "—");
  const destination = asString(journey?.destination_airport_code ?? journey?.destination, "—");
  const stops = asStops(journey);
  const stopsLabel = asString(
    journey?.stops_label_display ?? journey?.stops_display,
    stops === 0 ? "Direct" : stops === 1 ? "1 stop" : `${stops} stops`,
  );
  const offset = asString(journey?.arrival_day_offset_display ?? journey?.arrival_day_offset);
  const airlineCode = asString(
    journey?.airline_code ?? fallback?.airline_code ?? fromSeg.code,
  );
  const airlineName = asString(
    journey?.airline_name ?? fallback?.airline_name ?? fromSeg.name,
  );
  const logo =
    (typeof journey?.airline_logo_url === "string" ? journey.airline_logo_url : null)
    ?? (typeof fallback?.airline_logo_url === "string" ? fallback.airline_logo_url : null)
    ?? null;

  return {
    departure_time_display: asString(journey?.departure_time_display ?? journey?.departure_time, "—"),
    arrival_time_display: asString(journey?.arrival_time_display ?? journey?.arrival_time, "—"),
    departure_date_display: asString(journey?.departure_date_display ?? journey?.departure_date),
    arrival_date_display: asString(journey?.arrival_date_display ?? journey?.arrival_date),
    duration_display: asString(journey?.duration_display ?? journey?.duration),
    origin_airport_code: origin,
    destination_airport_code: destination,
    stops,
    stops_label_display: stopsLabel,
    layover_summary_display: asLayoverSummary(journey),
    arrival_day_offset_display: offset || undefined,
    airline_code: airlineCode,
    airline_name: airlineName,
    airline_logo_url: logo,
    flight_number: asString(journey?.flight_number),
  };
}
