"use client";

import type { ActiveResultsFilters, ResultsFilterMeta } from "../types";

type ResultsFilterPanelProps = {
  facets: ResultsFilterMeta | undefined;
  filters: ActiveResultsFilters;
  onChange: (filters: ActiveResultsFilters) => void;
  onClearAll: () => void;
  id?: string;
};

export function ResultsFilterPanel({ facets, filters, onChange, onClearAll, id }: ResultsFilterPanelProps) {
  const activeCount = Object.values(filters).filter(Boolean).length;

  return (
    <aside
      id={id}
      className="space-y-4 rounded-jp-card border border-jp-border bg-jp-surface p-4"
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

      {facets?.stops?.length ? (
        <fieldset className="space-y-2">
          <legend className="text-xs font-medium text-jp-text-muted">Stops</legend>
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
          {filters.stops ? (
            <button type="button" className="text-xs text-jp-primary" onClick={() => onChange({ ...filters, stops: undefined })}>
              Remove
            </button>
          ) : null}
        </fieldset>
      ) : null}

      {facets?.airlines?.length ? (
        <fieldset className="space-y-2">
          <legend className="text-xs font-medium text-jp-text-muted">Airlines</legend>
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
          {filters.airline ? (
            <button type="button" className="text-xs text-jp-primary" onClick={() => onChange({ ...filters, airline: undefined })}>
              Remove
            </button>
          ) : null}
        </fieldset>
      ) : null}

      {facets?.departure_windows?.length ? (
        <fieldset className="space-y-2">
          <legend className="text-xs font-medium text-jp-text-muted">Departure time</legend>
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
        </fieldset>
      ) : null}

      {facets?.refundable?.length ? (
        <fieldset className="space-y-2">
          <legend className="text-xs font-medium text-jp-text-muted">Refundability</legend>
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
        </fieldset>
      ) : null}
    </aside>
  );
}
