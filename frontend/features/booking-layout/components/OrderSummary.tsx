import { cn } from "@/lib/cn";
import type { AuthoritativePricing } from "@/features/standard-booking/types/review-payment";
import type { SelectedFlightSummary } from "@/features/standard-booking/types";

export type OrderSummaryProps = {
  itinerary?: SelectedFlightSummary | null;
  pricing?: AuthoritativePricing | null;
  travellerTotal?: number;
  paymentStatus?: { label: string; code?: string } | null;
  collapsed?: boolean;
  showEdit?: boolean;
  onEdit?: () => void;
  onChangeFlight?: () => void;
  changeFlightDisabled?: boolean;
  className?: string;
  testId?: string;
  variant?: "order" | "flight-preview";
};

function segmentValue(segment: Record<string, unknown> | undefined, keys: string[]): string | null {
  if (!segment) return null;
  for (const key of keys) {
    const value = segment[key];
    if (typeof value === "string" && value.trim()) return value.trim();
  }
  return null;
}

function formatStops(stops: number | null | undefined): string | null {
  if (stops == null) return null;
  return stops === 0 ? "Direct" : `${stops} Stop${stops === 1 ? "" : "s"}`;
}

function formatWholePkr(currency: string, amount: string | null | undefined): string | null {
  if (!amount) return null;
  const trimmed = amount.trim();
  if (!trimmed) return null;
  if (/pkr/i.test(trimmed)) return trimmed;
  return `${currency || "PKR"} ${trimmed}`;
}

function ItineraryRows({ itinerary, travellerTotal }: { itinerary: SelectedFlightSummary; travellerTotal?: number }) {
  return (
    <div className="space-y-2 text-jp-sm">
      <p className="font-medium text-jp-text">
        {itinerary.route_label ?? `${itinerary.origin} → ${itinerary.destination}`}
      </p>
      <dl className="space-y-1">
        {itinerary.airline_name || itinerary.airline_code ? (
          <div className="flex justify-between gap-2">
            <dt className="text-jp-muted">Airline</dt>
            <dd>{itinerary.airline_name ?? itinerary.airline_code}</dd>
          </div>
        ) : null}
        {itinerary.flight_number ? (
          <div className="flex justify-between gap-2">
            <dt className="text-jp-muted">Flight</dt>
            <dd>{itinerary.flight_number}</dd>
          </div>
        ) : null}
        {itinerary.depart_date ? (
          <div className="flex justify-between gap-2">
            <dt className="text-jp-muted">Depart</dt>
            <dd>{itinerary.depart_date}</dd>
          </div>
        ) : null}
        {itinerary.return_date ? (
          <div className="flex justify-between gap-2">
            <dt className="text-jp-muted">Return</dt>
            <dd>{itinerary.return_date}</dd>
          </div>
        ) : null}
        {itinerary.fare_family ? (
          <div className="flex justify-between gap-2">
            <dt className="text-jp-muted">Fare</dt>
            <dd>{itinerary.fare_family}</dd>
          </div>
        ) : null}
        {itinerary.baggage ? (
          <div className="flex justify-between gap-2">
            <dt className="text-jp-muted">Baggage</dt>
            <dd>{itinerary.baggage}</dd>
          </div>
        ) : null}
        {travellerTotal != null ? (
          <div className="flex justify-between gap-2">
            <dt className="text-jp-muted">Passengers</dt>
            <dd>{travellerTotal}</dd>
          </div>
        ) : null}
      </dl>
    </div>
  );
}

function FlightPreviewCard({
  itinerary,
  travellerTotal,
}: {
  itinerary: SelectedFlightSummary;
  travellerTotal?: number;
}) {
  const first = itinerary.segments?.[0];
  const last = itinerary.segments?.[itinerary.segments.length - 1];
  const departure =
    segmentValue(first, ["departure_time_display", "departure_time", "depart"]) ?? null;
  const arrival = segmentValue(last, ["arrival_time_display", "arrival_time", "arrive"]) ?? null;
  const origin = segmentValue(first, ["origin_airport_code", "origin"]) ?? itinerary.origin;
  const destination =
    segmentValue(last, ["destination_airport_code", "destination"]) ?? itinerary.destination;
  const stopsLabel = formatStops(itinerary.stops);
  const totalLabel = formatWholePkr(itinerary.currency, itinerary.total_formatted);
  const airline = itinerary.airline_name ?? itinerary.airline_code ?? "Airline";

  return (
    <div className="mt-3 space-y-3" data-testid="flight-preview-body">
      <div className="flex items-center gap-3">
        <div className="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-jp-md border border-jp-border-soft bg-jp-page">
          {itinerary.airline_logo_url ? (
            // eslint-disable-next-line @next/next/no-img-element -- supplier logo URL is dynamic
            <img src={itinerary.airline_logo_url} alt="" className="h-8 w-8 object-contain" />
          ) : (
            <span className="text-xs font-bold text-jp-primary">{(itinerary.airline_code ?? "JP").slice(0, 2)}</span>
          )}
        </div>
        <div className="min-w-0">
          <p className="truncate text-sm font-semibold text-jp-text">{airline}</p>
          <p className="text-xs text-jp-muted" data-testid="flight-preview-flight-number">
            {itinerary.flight_number ?? "Flight number not supplied"}
          </p>
        </div>
      </div>

      <div
        className="grid grid-cols-[1fr_auto_1fr] items-center gap-2 rounded-jp-md border border-jp-border-soft bg-jp-page px-3 py-3"
        data-testid="flight-preview-route"
      >
        <div>
          <p className="text-base font-bold tabular-nums text-jp-text">{departure ?? "—"}</p>
          <p className="text-xs font-semibold text-jp-muted">{origin}</p>
          {itinerary.depart_date ? (
            <p className="mt-0.5 text-[11px] text-jp-muted" data-testid="flight-preview-depart-date">
              {itinerary.depart_date}
            </p>
          ) : null}
        </div>
        <div className="flex min-w-[4.5rem] flex-col items-center gap-1" aria-hidden="true">
          <div className="flex w-full items-center">
            <span className="h-px flex-1 bg-jp-border" />
            <span className="mx-1 text-[10px] text-jp-primary">●</span>
            <span className="h-px flex-1 bg-jp-border" />
          </div>
          {stopsLabel ? (
            <span className="text-[10px] font-medium uppercase tracking-wide text-jp-muted">{stopsLabel}</span>
          ) : null}
        </div>
        <div className="text-right">
          <p className="text-base font-bold tabular-nums text-jp-text">{arrival ?? "—"}</p>
          <p className="text-xs font-semibold text-jp-muted">{destination}</p>
          {itinerary.return_date ? (
            <p className="mt-0.5 text-[11px] text-jp-muted">{itinerary.return_date}</p>
          ) : null}
        </div>
      </div>

      <div className="flex flex-wrap gap-1.5 text-xs text-jp-muted">
        {itinerary.duration ? (
          <span className="rounded-jp-pill bg-jp-page px-2 py-1" data-testid="flight-preview-duration">
            {itinerary.duration}
          </span>
        ) : null}
        {stopsLabel ? (
          <span className="rounded-jp-pill bg-jp-page px-2 py-1" data-testid="flight-preview-stops">
            {stopsLabel}
          </span>
        ) : null}
        {itinerary.cabin ? (
          <span className="rounded-jp-pill bg-jp-page px-2 py-1 capitalize">{itinerary.cabin}</span>
        ) : null}
      </div>

      <dl className="space-y-1.5 border-t border-jp-border pt-3 text-jp-sm">
        {itinerary.fare_family ? (
          <div className="flex justify-between gap-2">
            <dt className="text-jp-muted">Selected fare</dt>
            <dd className="font-medium text-jp-text" data-testid="flight-preview-fare">
              {itinerary.fare_family}
            </dd>
          </div>
        ) : null}
        {itinerary.cabin_baggage ? (
          <div className="flex justify-between gap-2">
            <dt className="text-jp-muted">Cabin baggage</dt>
            <dd data-testid="flight-preview-cabin-baggage">{itinerary.cabin_baggage}</dd>
          </div>
        ) : null}
        {itinerary.checked_baggage || itinerary.baggage ? (
          <div className="flex justify-between gap-2">
            <dt className="text-jp-muted">Checked baggage</dt>
            <dd data-testid="flight-preview-baggage">{itinerary.checked_baggage ?? itinerary.baggage}</dd>
          </div>
        ) : null}
        {itinerary.meal ? (
          <div className="flex justify-between gap-2">
            <dt className="text-jp-muted">Meal</dt>
            <dd data-testid="flight-preview-meal">{itinerary.meal}</dd>
          </div>
        ) : null}
        {travellerTotal != null ? (
          <div className="flex justify-between gap-2">
            <dt className="text-jp-muted">Passengers</dt>
            <dd data-testid="flight-preview-pax">{travellerTotal}</dd>
          </div>
        ) : null}
      </dl>

      {totalLabel ? (
        <p
          className="rounded-jp-md bg-jp-primary/5 px-3 py-3 text-lg font-bold tabular-nums text-jp-primary"
          data-testid="order-summary-total"
        >
          {totalLabel}
        </p>
      ) : null}
    </div>
  );
}

function PriceRows({ pricing }: { pricing: AuthoritativePricing }) {
  return (
    <dl className="mt-4 space-y-2 border-t border-jp-border pt-3 text-jp-sm">
      <div className="flex justify-between gap-4">
        <dt className="text-jp-muted">Base fare</dt>
        <dd>
          {pricing.currency} {pricing.base_fare.toLocaleString()}
        </dd>
      </div>
      <div className="flex justify-between gap-4">
        <dt className="text-jp-muted">Taxes &amp; fees</dt>
        <dd>
          {pricing.currency} {pricing.taxes.toLocaleString()}
        </dd>
      </div>
      {pricing.service_charges > 0 ? (
        <div className="flex justify-between gap-4">
          <dt className="text-jp-muted">Service charges</dt>
          <dd>
            {pricing.currency} {pricing.service_charges.toLocaleString()}
          </dd>
        </div>
      ) : null}
      <div className="mt-3 flex items-center justify-between gap-4 rounded-jp-md bg-jp-primary/5 px-3 py-3 font-semibold text-jp-text">
        <dt>Total</dt>
        <dd className="text-lg font-bold text-jp-primary" data-testid="order-summary-total">
          {pricing.formatted_total}
        </dd>
      </div>
    </dl>
  );
}

export function OrderSummary({
  itinerary,
  pricing,
  travellerTotal,
  paymentStatus,
  collapsed = false,
  showEdit = false,
  onEdit,
  onChangeFlight,
  changeFlightDisabled = false,
  className,
  testId = "order-summary",
  variant = "order",
}: OrderSummaryProps) {
  const preview = variant === "flight-preview";

  return (
    <article
      className={cn(
        "rounded-jp-lg border border-jp-border bg-jp-surface p-4",
        preview && "overflow-hidden shadow-jp-card",
        className,
      )}
      data-testid={testId}
    >
      <div className="flex items-center justify-between gap-2">
        <div>
          {preview ? (
            <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-jp-primary">Your selection</p>
          ) : null}
          <h2 className={cn("font-semibold text-jp-text", preview ? "mt-0.5 text-lg" : "text-jp-sm")}>
            {preview ? "Flight summary" : "Order summary"}
          </h2>
        </div>
        {showEdit && onEdit ? (
          <button
            type="button"
            className="text-jp-xs font-medium text-jp-primary hover:underline focus-visible:outline-none focus-visible:shadow-jp-focus"
            onClick={onEdit}
          >
            Edit
          </button>
        ) : null}
      </div>

      {!collapsed && itinerary ? (
        preview ? (
          <FlightPreviewCard itinerary={itinerary} travellerTotal={travellerTotal} />
        ) : (
          <div className="mt-3">
            <ItineraryRows itinerary={itinerary} travellerTotal={travellerTotal} />
          </div>
        )
      ) : null}

      {pricing ? <PriceRows pricing={pricing} /> : null}

      {!preview && !pricing && itinerary?.total_formatted ? (
        <p
          className="mt-3 rounded-jp-md bg-jp-primary/5 px-3 py-3 text-lg font-bold text-jp-primary"
          data-testid="order-summary-total"
        >
          {itinerary.currency} {itinerary.total_formatted}
        </p>
      ) : null}

      {paymentStatus ? (
        <p className="mt-3 text-jp-xs text-jp-muted">
          Payment: <span className="font-medium text-jp-text">{paymentStatus.label}</span>
        </p>
      ) : null}

      {onChangeFlight ? (
        <button
          type="button"
          className="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-jp-md border border-jp-border bg-white px-3 py-2.5 text-sm font-semibold text-jp-text hover:border-jp-primary hover:text-jp-primary focus-visible:outline-none focus-visible:shadow-jp-focus disabled:cursor-not-allowed disabled:opacity-50"
          data-testid="change-flight-button"
          onClick={onChangeFlight}
          disabled={changeFlightDisabled}
          aria-disabled={changeFlightDisabled}
        >
          <span aria-hidden="true">↔</span>
          Change flight
        </button>
      ) : null}
    </article>
  );
}
