"use client";

import type { ActiveResultsFilters, ResultsFilterMeta } from "../types";
import { formatWholePkr } from "../utils/price";

type ResultsFilterPanelProps = {
  facets: ResultsFilterMeta | undefined;
  filters: ActiveResultsFilters;
  onChange: (filters: ActiveResultsFilters) => void;
  onClearAll: () => void;
  id?: string;
  loading?: boolean;
  variant?: "sidebar" | "drawer";
};

function FacetGroup({
  legend,
  children,
}: {
  legend: string;
  children: React.ReactNode;
}) {
  return (
    <fieldset className="space-y-1.5 border-b border-jp-border-soft pb-2.5 last:border-b-0">
      <legend className="mb-1 text-[11px] font-bold uppercase tracking-[0.1em] text-jp-text-muted">{legend}</legend>
      {children}
    </fieldset>
  );
}

function FacetChoice({
  name,
  checked,
  onChange,
  label,
  count,
  multiple = true,
}: {
  name: string;
  checked: boolean;
  onChange: () => void;
  label: string;
  count: number;
  multiple?: boolean;
}) {
  return (
    <label className="group flex min-h-7 cursor-pointer items-center gap-2 text-[13px] text-jp-text">
      <input
        type={multiple ? "checkbox" : "radio"}
        name={name}
        checked={checked}
        onChange={onChange}
        aria-label={`${label} (${count})`}
        className="h-3.5 w-3.5 shrink-0 accent-jp-primary"
      />
      <span className="min-w-0 flex-1 truncate group-hover:text-jp-primary">{label}</span>
      <span aria-hidden="true" className="shrink-0 rounded-full bg-jp-surface-muted px-1.5 py-0.5 text-[10px] tabular-nums text-jp-text-muted">
        {count}
      </span>
      <span className="sr-only"> ({count})</span>
    </label>
  );
}

function selectedValues(value?: string): string[] {
  return value?.split(",").map((item) => item.trim()).filter(Boolean) ?? [];
}

function toggleValue(current: string | undefined, value: string): string | undefined {
  const next = new Set(selectedValues(current));
  if (next.has(value)) next.delete(value); else next.add(value);
  return next.size ? [...next].join(",") : undefined;
}

const STOP_LABELS: Record<string, string> = { direct: "Direct", "1_stop": "1 Stop", "2_plus": "2+ Stops" };
const REFUND_LABELS: Record<string, string> = { true: "Refundable", false: "Non-refundable", "1": "Refundable", "0": "Non-refundable" };

function PriceRange({ min, max, filters, onChange }: { min: number; max: number; filters: ActiveResultsFilters; onChange: (filters: ActiveResultsFilters) => void }) {
  const floor = Math.round(min);
  const ceiling = Math.max(floor + 1, Math.round(max));
  const selectedMin = Math.min(Number(filters.min_price ?? floor), Number(filters.max_price ?? ceiling));
  const selectedMax = Math.max(Number(filters.max_price ?? ceiling), selectedMin);
  const span = ceiling - floor;
  const left = ((selectedMin - floor) / span) * 100;
  const right = 100 - ((selectedMax - floor) / span) * 100;
  return <div data-testid="price-range-slider">
    <div className="flex justify-between gap-3 text-[11px] font-semibold text-jp-text"><span>{formatWholePkr(selectedMin)}</span><span>{formatWholePkr(selectedMax)}</span></div>
    <div className="relative mt-3 h-6">
      <div className="absolute left-0 right-0 top-2 h-1 rounded-full bg-jp-border-soft" />
      <div className="absolute top-2 h-1 rounded-full bg-jp-primary" style={{ left: `${left}%`, right: `${right}%` }} />
      <input type="range" min={floor} max={ceiling} value={selectedMin} aria-label="Minimum price" className="jp-dual-range absolute inset-x-0 top-0 w-full" onChange={(event) => { const value = Math.min(Number(event.target.value), selectedMax); onChange({ ...filters, min_price: String(value) }); }} />
      <input type="range" min={floor} max={ceiling} value={selectedMax} aria-label="Maximum price" className="jp-dual-range absolute inset-x-0 top-0 w-full" onChange={(event) => { const value = Math.max(Number(event.target.value), selectedMin); onChange({ ...filters, max_price: String(value) }); }} />
    </div>
  </div>;
}

export function ResultsFilterPanel({ facets, filters, onChange, onClearAll, id, loading, variant = "sidebar" }: ResultsFilterPanelProps) {
  const activeCount = Object.values(filters).filter(Boolean).length;

  return (
    <aside
      id={id}
      className={`${variant === "sidebar" ? "sticky top-20 rounded-jp-card border border-jp-border shadow-sm" : ""} space-y-2.5 bg-jp-surface p-3.5`}
      aria-label="Filter results"
      data-testid="results-filter-panel"
    >
      <div className="flex items-center justify-between border-b border-jp-border-soft pb-2.5">
        <div>
          <h2 className="text-sm font-semibold text-jp-text">Filter results</h2>
          <p className="text-[11px] text-jp-text-muted">{activeCount ? `${activeCount} active` : "Refine your options"}</p>
        </div>
        {activeCount > 0 ? (
          <button
            type="button"
            className="text-xs font-medium text-jp-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
            onClick={onClearAll}
            data-testid="clear-all-filters"
          >
            Clear all
          </button>
        ) : null}
      </div>

      {loading && !facets ? (
        <div className="space-y-3" data-testid="filter-skeleton" aria-hidden="true">
          <div className="h-16 animate-pulse rounded bg-jp-border-soft" />
          <div className="h-24 animate-pulse rounded bg-jp-border-soft" />
        </div>
      ) : null}

      <label className="block text-xs font-medium text-jp-text-muted">
        Flight number
        <input
          type="search"
          value={filters.flight_number ?? ""}
          onChange={(event) => onChange({ ...filters, flight_number: event.target.value || undefined })}
          className="mt-1 w-full rounded-jp-md border border-jp-border px-2 py-1.5 text-sm text-jp-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
          placeholder="e.g. EK612"
          data-testid="filter-flight-number"
        />
      </label>

      {facets?.stops?.length ? (
        <FacetGroup legend="Stops">
          {facets.stops.map((item) => (
            <FacetChoice key={item.value} name="stops-filter" checked={selectedValues(filters.stops).includes(item.value)} onChange={() => onChange({ ...filters, stops: toggleValue(filters.stops, item.value) })} label={item.label || STOP_LABELS[item.value] || item.value} count={item.count} />
          ))}
        </FacetGroup>
      ) : null}

      {facets?.price_range ? (
        <FacetGroup legend="Price range">
          <PriceRange min={facets.price_range.min} max={facets.price_range.max} filters={filters} onChange={onChange} />
        </FacetGroup>
      ) : null}

      {facets?.airlines?.length ? (
        <FacetGroup legend="Airlines">
          <div className="space-y-2">
            {facets.airlines.map((item) => (
              <FacetChoice key={item.code} name="airline-filter" checked={selectedValues(filters.airline).includes(item.code)} onChange={() => onChange({ ...filters, airline: toggleValue(filters.airline, item.code) })} label={item.name || item.code} count={item.count} />
            ))}
          </div>
        </FacetGroup>
      ) : null}

      {facets?.departure_windows?.length ? (
        <FacetGroup legend="Departure time">
          {facets.departure_windows.map((item) => (
            <FacetChoice key={item.value} name="departure-window-filter" checked={selectedValues(filters.departure_window).includes(item.value)} onChange={() => onChange({ ...filters, departure_window: toggleValue(filters.departure_window, item.value) })} label={item.label || item.value.replaceAll("_", " ")} count={item.count} />
          ))}
        </FacetGroup>
      ) : null}

      {facets?.arrival_windows?.length ? (
        <FacetGroup legend="Arrival time">
          {facets.arrival_windows.map((item) => (
            <FacetChoice key={item.value} name="arrival-window-filter" checked={selectedValues(filters.arrival_window).includes(item.value)} onChange={() => onChange({ ...filters, arrival_window: toggleValue(filters.arrival_window, item.value) })} label={item.label || item.value.replaceAll("_", " ")} count={item.count} />
          ))}
        </FacetGroup>
      ) : null}

      {facets?.refundable?.length ? (
        <FacetGroup legend="Refundability">
          {facets.refundable.map((item) => (
            <FacetChoice key={String(item.value)} name="refundable-filter" checked={selectedValues(filters.refundable).includes(String(item.value))} onChange={() => onChange({ ...filters, refundable: toggleValue(filters.refundable, String(item.value)) })} label={item.label || REFUND_LABELS[String(item.value)] || String(item.value)} count={item.count} />
          ))}
        </FacetGroup>
      ) : null}

      {facets?.cabin_classes?.length ? (
        <FacetGroup legend="Cabin">
          {facets.cabin_classes.map((item) => (
            <FacetChoice key={item.value} name="cabin-filter" multiple={false} checked={filters.cabin === item.value} onChange={() => onChange({ ...filters, cabin: item.value })} label={item.label || item.value} count={item.count} />
          ))}
        </FacetGroup>
      ) : null}

      {facets?.baggage_options?.length ? (
        <FacetGroup legend="Baggage">
          {facets.baggage_options.map((item) => (
            <FacetChoice key={item.value} name="baggage-filter" checked={filters.baggage === item.value} onChange={() => onChange({ ...filters, baggage: item.value })} label={item.label} count={item.count} />
          ))}
        </FacetGroup>
      ) : null}

      {facets?.fare_families?.length ? (
        <FacetGroup legend="Fare family">
          {facets.fare_families.map((item) => (
            <FacetChoice key={item.value} name="fare-family-filter" checked={filters.fare_family === item.value} onChange={() => onChange({ ...filters, fare_family: item.value })} label={item.label} count={item.count} />
          ))}
        </FacetGroup>
      ) : null}

      {facets?.duration_buckets?.length ? (
        <FacetGroup legend="Duration">
          {facets.duration_buckets.map((item) => (
            <FacetChoice key={item.value} name="duration-filter" checked={filters.duration_bucket === item.value} onChange={() => onChange({ ...filters, duration_bucket: item.value })} label={item.label} count={item.count} />
          ))}
        </FacetGroup>
      ) : null}

      {facets?.layover_airports?.length ? (
        <FacetGroup legend="Layover airport">
          {facets.layover_airports.map((item) => (
            <FacetChoice key={item.code} name="layover-filter" checked={filters.layover_airport === item.code} onChange={() => onChange({ ...filters, layover_airport: item.code })} label={item.name} count={item.count} />
          ))}
        </FacetGroup>
      ) : null}

      <label className="flex min-h-8 cursor-pointer items-center gap-2 rounded-jp-md bg-jp-surface-muted px-2 text-[13px] font-medium text-jp-text">
        <input
          type="checkbox"
          checked={filters.bookable_only === "1"}
          onChange={(event) => onChange({ ...filters, bookable_only: event.target.checked ? "1" : undefined })}
          className="h-3.5 w-3.5 accent-jp-primary"
        />
        Bookable online only
      </label>
    </aside>
  );
}
