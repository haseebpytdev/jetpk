"use client";

import type { ActiveResultsFilters, ResultsFilterMeta } from "../types";

type ResultsFilterPanelProps = {
  facets: ResultsFilterMeta | undefined;
  filters: ActiveResultsFilters;
  onChange: (filters: ActiveResultsFilters) => void;
  onClearAll: () => void;
  id?: string;
  loading?: boolean;
};

function FacetGroup({
  legend,
  children,
}: {
  legend: string;
  children: React.ReactNode;
}) {
  return (
    <fieldset className="space-y-2 border-b border-jp-border-soft pb-3 last:border-b-0">
      <legend className="text-xs font-semibold uppercase tracking-wide text-jp-text-muted">{legend}</legend>
      {children}
    </fieldset>
  );
}

export function ResultsFilterPanel({ facets, filters, onChange, onClearAll, id, loading }: ResultsFilterPanelProps) {
  const activeCount = Object.values(filters).filter(Boolean).length;

  return (
    <aside
      id={id}
      className="sticky top-20 space-y-3 rounded-jp-card border border-jp-border bg-jp-surface p-3"
      aria-label="Filter results"
      data-testid="results-filter-panel"
    >
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-semibold text-jp-text">Filters</h2>
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
          className="mt-1 w-full rounded-jp-md border border-jp-border px-2 py-1.5 text-sm text-jp-text"
          placeholder="e.g. EK612"
          data-testid="filter-flight-number"
        />
      </label>

      {facets?.stops?.length ? (
        <FacetGroup legend="Stops">
          {facets.stops.map((item) => (
            <label key={item.value} className="flex items-center gap-2 text-sm">
              <input
                type="radio"
                name="stops-filter"
                checked={filters.stops === item.value}
                onChange={() => onChange({ ...filters, stops: item.value })}
              />
              {item.label} ({item.count})
            </label>
          ))}
        </FacetGroup>
      ) : null}

      {facets?.price_range ? (
        <FacetGroup legend="Price range">
          <div className="flex gap-2">
            <input
              type="number"
              aria-label="Minimum price"
              className="w-full rounded-jp-md border border-jp-border px-2 py-1 text-sm"
              value={filters.min_price ?? ""}
              placeholder={String(facets.price_range.min)}
              onChange={(event) => onChange({ ...filters, min_price: event.target.value || undefined })}
            />
            <input
              type="number"
              aria-label="Maximum price"
              className="w-full rounded-jp-md border border-jp-border px-2 py-1 text-sm"
              value={filters.max_price ?? ""}
              placeholder={String(facets.price_range.max)}
              onChange={(event) => onChange({ ...filters, max_price: event.target.value || undefined })}
            />
          </div>
        </FacetGroup>
      ) : null}

      {facets?.airlines?.length ? (
        <FacetGroup legend="Airlines">
          <div className="max-h-40 space-y-2 overflow-y-auto">
            {facets.airlines.map((item) => (
              <label key={item.code} className="flex items-center gap-2 text-sm">
                <input
                  type="radio"
                  name="airline-filter"
                  checked={filters.airline === item.code}
                  onChange={() => onChange({ ...filters, airline: item.code })}
                />
                {item.name} ({item.count})
              </label>
            ))}
          </div>
        </FacetGroup>
      ) : null}

      {facets?.departure_windows?.length ? (
        <FacetGroup legend="Departure time">
          {facets.departure_windows.map((item) => (
            <label key={item.value} className="flex items-center gap-2 text-sm">
              <input
                type="radio"
                name="departure-window-filter"
                checked={filters.departure_window === item.value}
                onChange={() => onChange({ ...filters, departure_window: item.value })}
              />
              {item.label} ({item.count})
            </label>
          ))}
        </FacetGroup>
      ) : null}

      {facets?.arrival_windows?.length ? (
        <FacetGroup legend="Arrival time">
          {facets.arrival_windows.map((item) => (
            <label key={item.value} className="flex items-center gap-2 text-sm">
              <input
                type="radio"
                name="arrival-window-filter"
                checked={filters.arrival_window === item.value}
                onChange={() => onChange({ ...filters, arrival_window: item.value })}
              />
              {item.label} ({item.count})
            </label>
          ))}
        </FacetGroup>
      ) : null}

      {facets?.refundable?.length ? (
        <FacetGroup legend="Refundability">
          {facets.refundable.map((item) => (
            <label key={item.value} className="flex items-center gap-2 text-sm">
              <input
                type="radio"
                name="refundable-filter"
                checked={filters.refundable === item.value}
                onChange={() => onChange({ ...filters, refundable: item.value })}
              />
              {item.label} ({item.count})
            </label>
          ))}
        </FacetGroup>
      ) : null}

      {facets?.cabin_classes?.length ? (
        <FacetGroup legend="Cabin">
          {facets.cabin_classes.map((item) => (
            <label key={item.value} className="flex items-center gap-2 text-sm">
              <input
                type="radio"
                name="cabin-filter"
                checked={filters.cabin === item.value}
                onChange={() => onChange({ ...filters, cabin: item.value })}
              />
              {item.label} ({item.count})
            </label>
          ))}
        </FacetGroup>
      ) : null}

      {facets?.baggage_options?.length ? (
        <FacetGroup legend="Baggage">
          {facets.baggage_options.map((item) => (
            <label key={item.value} className="flex items-center gap-2 text-sm">
              <input
                type="radio"
                name="baggage-filter"
                checked={filters.baggage === item.value}
                onChange={() => onChange({ ...filters, baggage: item.value })}
              />
              {item.label} ({item.count})
            </label>
          ))}
        </FacetGroup>
      ) : null}

      {facets?.fare_families?.length ? (
        <FacetGroup legend="Fare family">
          {facets.fare_families.map((item) => (
            <label key={item.value} className="flex items-center gap-2 text-sm">
              <input
                type="radio"
                name="fare-family-filter"
                checked={filters.fare_family === item.value}
                onChange={() => onChange({ ...filters, fare_family: item.value })}
              />
              {item.label} ({item.count})
            </label>
          ))}
        </FacetGroup>
      ) : null}

      {facets?.duration_buckets?.length ? (
        <FacetGroup legend="Duration">
          {facets.duration_buckets.map((item) => (
            <label key={item.value} className="flex items-center gap-2 text-sm">
              <input
                type="radio"
                name="duration-filter"
                checked={filters.duration_bucket === item.value}
                onChange={() => onChange({ ...filters, duration_bucket: item.value })}
              />
              {item.label} ({item.count})
            </label>
          ))}
        </FacetGroup>
      ) : null}

      {facets?.layover_airports?.length ? (
        <FacetGroup legend="Layover airport">
          {facets.layover_airports.map((item) => (
            <label key={item.code} className="flex items-center gap-2 text-sm">
              <input
                type="radio"
                name="layover-filter"
                checked={filters.layover_airport === item.code}
                onChange={() => onChange({ ...filters, layover_airport: item.code })}
              />
              {item.name} ({item.count})
            </label>
          ))}
        </FacetGroup>
      ) : null}

      <label className="flex items-center gap-2 text-sm">
        <input
          type="checkbox"
          checked={filters.bookable_only === "1"}
          onChange={(event) => onChange({ ...filters, bookable_only: event.target.checked ? "1" : undefined })}
        />
        Bookable online only
      </label>
    </aside>
  );
}
