"use client";

import { useEffect, useRef, useState } from "react";
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

const STOP_LABELS: Record<string, string> = { direct: "Direct", "1_stop": "1 Stop", "2_plus": "2+ Stops" };
const REFUND_LABELS: Record<string, string> = { true: "Refundable", false: "Non-refundable", "1": "Refundable", "0": "Non-refundable" };
const DURATION_LABELS: Record<string, string> = {
  under_6h: "Under 6 hours",
  "6_12h": "6–12 hours",
  "12_18h": "12–18 hours",
  "12_20h": "12–18 hours",
  over_18h: "18+ hours",
  over_20h: "18+ hours",
};

const PRICE_DEBOUNCE_MS = 350;

function FacetGroup({
  legend,
  children,
}: {
  legend: string;
  children: React.ReactNode;
}) {
  return (
    <fieldset className="min-w-0 space-y-1.5 border-b border-jp-border-soft pb-2.5 last:border-b-0">
      <legend className="mb-1 text-[11px] font-bold uppercase tracking-[0.1em] text-jp-text-muted">{legend}</legend>
      <div className="min-w-0 space-y-1.5">{children}</div>
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
    <label className="group flex min-h-7 min-w-0 cursor-pointer items-start gap-2 text-[13px] text-jp-text">
      <input
        type={multiple ? "checkbox" : "radio"}
        name={name}
        checked={checked}
        onChange={onChange}
        aria-label={`${label} (${count})`}
        className="mt-0.5 h-3.5 w-3.5 shrink-0 accent-jp-primary"
      />
      <span className="min-w-0 flex-1 break-words leading-snug group-hover:text-jp-primary">{label}</span>
      <span
        aria-hidden="true"
        className="ml-auto shrink-0 rounded-full bg-jp-surface-muted px-1.5 py-0.5 text-right text-[10px] tabular-nums text-jp-text-muted"
      >
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
  if (next.has(value)) next.delete(value);
  else next.add(value);
  return next.size ? [...next].join(",") : undefined;
}

function durationLabel(item: { value: string; label?: string }): string {
  const explicit = item.label?.trim();
  if (explicit) return explicit;
  return DURATION_LABELS[item.value] ?? item.value.replaceAll("_", " ");
}

function PriceRange({
  min,
  max,
  filters,
  onChange,
}: {
  min: number;
  max: number;
  filters: ActiveResultsFilters;
  onChange: (filters: ActiveResultsFilters) => void;
}) {
  const floor = Math.round(min);
  const ceiling = Math.max(floor + 1, Math.round(max));
  const committedMin = Math.min(Number(filters.min_price ?? floor), Number(filters.max_price ?? ceiling));
  const committedMax = Math.max(Number(filters.max_price ?? ceiling), committedMin);
  const [localMin, setLocalMin] = useState(committedMin);
  const [localMax, setLocalMax] = useState(committedMax);
  const filtersRef = useRef(filters);
  filtersRef.current = filters;

  useEffect(() => {
    setLocalMin(committedMin);
    setLocalMax(committedMax);
  }, [committedMin, committedMax, floor, ceiling]);

  useEffect(() => {
    if (localMin === committedMin && localMax === committedMax) return;
    const timer = window.setTimeout(() => {
      onChange({
        ...filtersRef.current,
        min_price: String(localMin),
        max_price: String(localMax),
      });
    }, PRICE_DEBOUNCE_MS);
    return () => window.clearTimeout(timer);
  }, [localMin, localMax, committedMin, committedMax, onChange]);

  const span = ceiling - floor;
  const left = ((localMin - floor) / span) * 100;
  const right = 100 - ((localMax - floor) / span) * 100;

  return (
    <div data-testid="price-range-slider" className="min-w-0">
      <div className="flex min-w-0 justify-between gap-3 text-[11px] font-semibold text-jp-text">
        <span className="min-w-0 truncate">{formatWholePkr(localMin)}</span>
        <span className="min-w-0 truncate text-right">{formatWholePkr(localMax)}</span>
      </div>
      <div className="relative mt-3 h-6 min-w-0">
        <div className="absolute left-0 right-0 top-2 h-1 rounded-full bg-jp-border-soft" />
        <div className="absolute top-2 h-1 rounded-full bg-jp-primary" style={{ left: `${left}%`, right: `${right}%` }} />
        <input
          type="range"
          min={floor}
          max={ceiling}
          value={localMin}
          aria-label="Minimum price"
          className="jp-dual-range absolute inset-x-0 top-0 w-full max-w-full"
          onChange={(event) => {
            const value = Math.min(Number(event.target.value), localMax);
            setLocalMin(value);
          }}
        />
        <input
          type="range"
          min={floor}
          max={ceiling}
          value={localMax}
          aria-label="Maximum price"
          className="jp-dual-range absolute inset-x-0 top-0 w-full max-w-full"
          onChange={(event) => {
            const value = Math.max(Number(event.target.value), localMin);
            setLocalMax(value);
          }}
        />
      </div>
    </div>
  );
}

export function ResultsFilterPanel({
  facets,
  filters,
  onChange,
  onClearAll,
  id,
  loading,
  variant = "sidebar",
}: ResultsFilterPanelProps) {
  const activeCount = Object.values(filters).filter(Boolean).length;

  return (
    <aside
      id={id}
      className={`${variant === "sidebar" ? "sticky top-20 rounded-jp-card border border-jp-border shadow-sm" : ""} min-w-0 max-w-full space-y-2.5 overflow-x-hidden bg-jp-surface p-3.5`}
      aria-label="Filter results"
      data-testid="results-filter-panel"
    >
      <div className="flex min-w-0 items-center justify-between gap-2 border-b border-jp-border-soft pb-2.5">
        <div className="min-w-0">
          <h2 className="text-sm font-semibold text-jp-text">Filter results</h2>
          <p className="truncate text-[11px] text-jp-text-muted">
            {activeCount ? `${activeCount} active` : "Refine your options"}
          </p>
        </div>
        {activeCount > 0 ? (
          <button
            type="button"
            className="shrink-0 text-xs font-medium text-jp-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
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

      <label className="block min-w-0 text-xs font-medium text-jp-text-muted">
        Flight number
        <input
          type="search"
          value={filters.flight_number ?? ""}
          onChange={(event) => onChange({ ...filters, flight_number: event.target.value || undefined })}
          className="mt-1 w-full min-w-0 max-w-full rounded-jp-md border border-jp-border px-2 py-1.5 text-sm text-jp-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
          placeholder="e.g. EK612"
          data-testid="filter-flight-number"
        />
      </label>

      {facets?.stops?.length ? (
        <FacetGroup legend="Stops">
          {facets.stops.map((item) => (
            <FacetChoice
              key={item.value}
              name="stops-filter"
              checked={selectedValues(filters.stops).includes(item.value)}
              onChange={() => onChange({ ...filters, stops: toggleValue(filters.stops, item.value) })}
              label={item.label || STOP_LABELS[item.value] || item.value}
              count={item.count}
            />
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
          {facets.airlines.map((item) => (
            <FacetChoice
              key={item.code}
              name="airline-filter"
              checked={selectedValues(filters.airline).includes(item.code)}
              onChange={() => onChange({ ...filters, airline: toggleValue(filters.airline, item.code) })}
              label={item.name || item.code}
              count={item.count}
            />
          ))}
        </FacetGroup>
      ) : null}

      {facets?.departure_windows?.length ? (
        <FacetGroup legend="Departure time">
          {facets.departure_windows.map((item) => (
            <FacetChoice
              key={item.value}
              name="departure-window-filter"
              checked={selectedValues(filters.departure_window).includes(item.value)}
              onChange={() =>
                onChange({ ...filters, departure_window: toggleValue(filters.departure_window, item.value) })
              }
              label={item.label || item.value.replaceAll("_", " ")}
              count={item.count}
            />
          ))}
        </FacetGroup>
      ) : null}

      {facets?.arrival_windows?.length ? (
        <FacetGroup legend="Arrival time">
          {facets.arrival_windows.map((item) => (
            <FacetChoice
              key={item.value}
              name="arrival-window-filter"
              checked={selectedValues(filters.arrival_window).includes(item.value)}
              onChange={() =>
                onChange({ ...filters, arrival_window: toggleValue(filters.arrival_window, item.value) })
              }
              label={item.label || item.value.replaceAll("_", " ")}
              count={item.count}
            />
          ))}
        </FacetGroup>
      ) : null}

      {facets?.refundable?.length ? (
        <FacetGroup legend="Refundability">
          {facets.refundable.map((item) => (
            <FacetChoice
              key={String(item.value)}
              name="refundable-filter"
              checked={selectedValues(filters.refundable).includes(String(item.value))}
              onChange={() =>
                onChange({ ...filters, refundable: toggleValue(filters.refundable, String(item.value)) })
              }
              label={item.label || REFUND_LABELS[String(item.value)] || String(item.value)}
              count={item.count}
            />
          ))}
        </FacetGroup>
      ) : null}

      {facets?.cabin_classes?.length ? (
        <FacetGroup legend="Cabin">
          {facets.cabin_classes.map((item) => (
            <FacetChoice
              key={item.value}
              name="cabin-filter"
              multiple={false}
              checked={filters.cabin === item.value}
              onChange={() => onChange({ ...filters, cabin: item.value })}
              label={item.label || item.value}
              count={item.count}
            />
          ))}
        </FacetGroup>
      ) : null}

      {facets?.baggage_options?.length ? (
        <FacetGroup legend="Baggage">
          {facets.baggage_options.map((item) => (
            <FacetChoice
              key={item.value}
              name="baggage-filter"
              multiple={false}
              checked={filters.baggage === item.value}
              onChange={() => onChange({ ...filters, baggage: item.value })}
              label={item.label}
              count={item.count}
            />
          ))}
        </FacetGroup>
      ) : null}

      {facets?.fare_families?.length ? (
        <FacetGroup legend="Fare family">
          {facets.fare_families.map((item) => (
            <FacetChoice
              key={item.value}
              name="fare-family-filter"
              multiple={false}
              checked={filters.fare_family === item.value}
              onChange={() => onChange({ ...filters, fare_family: item.value })}
              label={item.label}
              count={item.count}
            />
          ))}
        </FacetGroup>
      ) : null}

      {facets?.duration_buckets?.length ? (
        <FacetGroup legend="Duration">
          {facets.duration_buckets.map((item) => (
            <FacetChoice
              key={item.value}
              name="duration-filter"
              multiple={false}
              checked={filters.duration_bucket === item.value}
              onChange={() => onChange({ ...filters, duration_bucket: item.value })}
              label={durationLabel(item)}
              count={item.count}
            />
          ))}
        </FacetGroup>
      ) : null}

      {facets?.layover_airports?.length ? (
        <FacetGroup legend="Layover airport">
          {facets.layover_airports.map((item) => (
            <FacetChoice
              key={item.code}
              name="layover-filter"
              multiple={false}
              checked={filters.layover_airport === item.code}
              onChange={() => onChange({ ...filters, layover_airport: item.code })}
              label={item.name}
              count={item.count}
            />
          ))}
        </FacetGroup>
      ) : null}

      <label className="flex min-h-8 min-w-0 cursor-pointer items-center gap-2 rounded-jp-md bg-jp-surface-muted px-2 text-[13px] font-medium text-jp-text">
        <input
          type="checkbox"
          checked={filters.bookable_only === "1"}
          onChange={(event) => onChange({ ...filters, bookable_only: event.target.checked ? "1" : undefined })}
          className="h-3.5 w-3.5 shrink-0 accent-jp-primary"
        />
        <span className="min-w-0 break-words">Bookable online only</span>
      </label>
    </aside>
  );
}
