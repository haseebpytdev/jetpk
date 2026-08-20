"use client";

import type { ActiveResultsFilters, ResultsFilterMeta } from "../types";

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
}: {
  name: string;
  checked: boolean;
  onChange: () => void;
  label: string;
  count: number;
}) {
  return (
    <label className="group flex min-h-7 cursor-pointer items-center gap-2 text-[13px] text-jp-text">
      <input
        type="radio"
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

export function ResultsFilterPanel({ facets, filters, onChange, onClearAll, id, loading, variant = "sidebar" }: ResultsFilterPanelProps) {
  const activeCount = Object.values(filters).filter(Boolean).length;

  return (
    <aside
      id={id}
      className={`${variant === "sidebar" ? "sticky top-20 max-h-[calc(100dvh-6rem)] overflow-y-auto rounded-jp-card border border-jp-border shadow-sm" : ""} space-y-2.5 bg-jp-surface p-3.5`}
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
            <FacetChoice key={item.value} name="stops-filter" checked={filters.stops === item.value} onChange={() => onChange({ ...filters, stops: item.value })} label={item.label} count={item.count} />
          ))}
        </FacetGroup>
      ) : null}

      {facets?.price_range ? (
        <FacetGroup legend="Price range">
          <div className="grid grid-cols-2 gap-2">
            <label className="text-[10px] font-medium text-jp-text-muted">Minimum (PKR)
              <input type="number" aria-label="Minimum price" className="mt-1 w-full rounded-jp-md border border-jp-border px-2 py-1.5 text-sm tabular-nums focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary" value={filters.min_price ?? ""} placeholder={String(facets.price_range.min)} onChange={(event) => onChange({ ...filters, min_price: event.target.value || undefined })} />
            </label>
            <label className="text-[10px] font-medium text-jp-text-muted">Maximum (PKR)
              <input type="number" aria-label="Maximum price" className="mt-1 w-full rounded-jp-md border border-jp-border px-2 py-1.5 text-sm tabular-nums focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary" value={filters.max_price ?? ""} placeholder={String(facets.price_range.max)} onChange={(event) => onChange({ ...filters, max_price: event.target.value || undefined })} />
            </label>
          </div>
        </FacetGroup>
      ) : null}

      {facets?.airlines?.length ? (
        <FacetGroup legend="Airlines">
          <div className="max-h-40 space-y-2 overflow-y-auto">
            {facets.airlines.map((item) => (
              <FacetChoice key={item.code} name="airline-filter" checked={filters.airline === item.code} onChange={() => onChange({ ...filters, airline: item.code })} label={item.name} count={item.count} />
            ))}
          </div>
        </FacetGroup>
      ) : null}

      {facets?.departure_windows?.length ? (
        <FacetGroup legend="Departure time">
          {facets.departure_windows.map((item) => (
            <FacetChoice key={item.value} name="departure-window-filter" checked={filters.departure_window === item.value} onChange={() => onChange({ ...filters, departure_window: item.value })} label={item.label} count={item.count} />
          ))}
        </FacetGroup>
      ) : null}

      {facets?.arrival_windows?.length ? (
        <FacetGroup legend="Arrival time">
          {facets.arrival_windows.map((item) => (
            <FacetChoice key={item.value} name="arrival-window-filter" checked={filters.arrival_window === item.value} onChange={() => onChange({ ...filters, arrival_window: item.value })} label={item.label} count={item.count} />
          ))}
        </FacetGroup>
      ) : null}

      {facets?.refundable?.length ? (
        <FacetGroup legend="Refundability">
          {facets.refundable.map((item) => (
            <FacetChoice key={item.value} name="refundable-filter" checked={filters.refundable === item.value} onChange={() => onChange({ ...filters, refundable: item.value })} label={item.label} count={item.count} />
          ))}
        </FacetGroup>
      ) : null}

      {facets?.cabin_classes?.length ? (
        <FacetGroup legend="Cabin">
          {facets.cabin_classes.map((item) => (
            <FacetChoice key={item.value} name="cabin-filter" checked={filters.cabin === item.value} onChange={() => onChange({ ...filters, cabin: item.value })} label={item.label} count={item.count} />
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
