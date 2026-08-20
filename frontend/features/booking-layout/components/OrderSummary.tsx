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
  className?: string;
  testId?: string;
  variant?: "order" | "flight-preview";
};

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

function PriceRows({ pricing }: { pricing: AuthoritativePricing }) {
  return (
    <dl className="mt-4 space-y-2 border-t border-jp-border pt-3 text-jp-sm">
      <div className="flex justify-between gap-4">
        <dt className="text-jp-muted">Base fare</dt>
        <dd>{pricing.currency} {pricing.base_fare.toLocaleString()}</dd>
      </div>
      <div className="flex justify-between gap-4">
        <dt className="text-jp-muted">Taxes &amp; fees</dt>
        <dd>{pricing.currency} {pricing.taxes.toLocaleString()}</dd>
      </div>
      {pricing.service_charges > 0 ? (
        <div className="flex justify-between gap-4">
          <dt className="text-jp-muted">Service charges</dt>
          <dd>{pricing.currency} {pricing.service_charges.toLocaleString()}</dd>
        </div>
      ) : null}
      <div className="flex justify-between gap-4 border-t border-jp-border pt-2 font-semibold text-jp-text">
        <dt>Total</dt>
        <dd data-testid="order-summary-total">{pricing.formatted_total}</dd>
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
  className,
  testId = "order-summary",
  variant = "order",
}: OrderSummaryProps) {
  const preview = variant === "flight-preview";
  return (
    <article
      className={cn("rounded-jp-lg border border-jp-border bg-jp-surface p-4", preview && "overflow-hidden shadow-jp-card", className)}
      data-testid={testId}
    >
      <div className="flex items-center justify-between gap-2">
        <div>
          {preview ? <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-jp-primary">Your selection</p> : null}
          <h2 className={cn("font-semibold text-jp-text", preview ? "mt-0.5 text-lg" : "text-jp-sm")}>{preview ? "Flight preview" : "Order summary"}</h2>
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
        <div className="mt-3">
          {preview && (itinerary.airline_name || itinerary.airline_code) ? (
            <div className="mb-3 flex items-center gap-3 rounded-jp-md bg-jp-page p-3">
              {itinerary.airline_logo_url ? <img src={itinerary.airline_logo_url} alt="" className="h-9 w-9 object-contain" /> : null}
              <div className="min-w-0"><p className="truncate text-sm font-semibold text-jp-text">{itinerary.airline_name ?? itinerary.airline_code}</p><p className="text-xs text-jp-muted">{itinerary.flight_number ?? "Flight number not supplied"}</p></div>
            </div>
          ) : null}
          <ItineraryRows itinerary={itinerary} travellerTotal={travellerTotal} />
          {preview && (itinerary.duration || itinerary.stops != null || itinerary.cabin) ? (
            <div className="mt-3 flex flex-wrap gap-2 border-t border-jp-border pt-3 text-xs text-jp-muted">
              {itinerary.duration ? <span className="rounded-full bg-jp-page px-2 py-1">{itinerary.duration}</span> : null}
              {itinerary.stops != null ? <span className="rounded-full bg-jp-page px-2 py-1">{itinerary.stops === 0 ? "Nonstop" : `${itinerary.stops} stop${itinerary.stops === 1 ? "" : "s"}`}</span> : null}
              {itinerary.cabin ? <span className="rounded-full bg-jp-page px-2 py-1">{itinerary.cabin}</span> : null}
            </div>
          ) : null}
        </div>
      ) : null}

      {pricing ? <PriceRows pricing={pricing} /> : null}

      {!pricing && itinerary?.total_formatted ? (
        <p className="mt-3 border-t border-jp-border pt-3 text-lg font-semibold text-jp-text" data-testid="order-summary-total">
          {itinerary.currency} {itinerary.total_formatted}
        </p>
      ) : null}

      {paymentStatus ? (
        <p className="mt-3 text-jp-xs text-jp-muted">
          Payment: <span className="font-medium text-jp-text">{paymentStatus.label}</span>
        </p>
      ) : null}
    </article>
  );
}
