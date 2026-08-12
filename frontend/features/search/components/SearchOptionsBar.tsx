"use client";

import { cn } from "@/lib/cn";
import type { SearchOptions } from "../types";

type SearchOptionsBarProps = {
  options: SearchOptions;
  onChange: (options: SearchOptions) => void;
  showFlexibleDates?: boolean;
  className?: string;
  compact?: boolean;
};

function OptionToggle({
  id,
  label,
  checked,
  onChange,
}: {
  id: string;
  label: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
}) {
  return (
    <label htmlFor={id} className="inline-flex cursor-pointer items-center gap-2 text-jp-sm font-medium text-jp-text">
      <input
        id={id}
        type="checkbox"
        checked={checked}
        onChange={(event) => onChange(event.target.checked)}
        className="h-4 w-4 rounded border-jp-border text-jp-primary accent-jp-brand focus-visible:outline-none focus-visible:shadow-jp-focus"
      />
      <span>{label}</span>
    </label>
  );
}

export function SearchOptionsBar({
  options,
  onChange,
  showFlexibleDates = true,
  className,
  compact = false,
}: SearchOptionsBarProps) {
  const update = (patch: Partial<SearchOptions>) => onChange({ ...options, ...patch });

  return (
    <div className={cn(compact ? "flex flex-wrap gap-x-4 gap-y-1 text-jp-xs" : "flex flex-wrap gap-x-5 gap-y-2", className)}>
      <OptionToggle
        id="search-direct-only"
        label="Direct Flights Only"
        checked={options.directFlightsOnly}
        onChange={(checked) => update({ directFlightsOnly: checked })}
      />
      <OptionToggle
        id="search-nearby-airports"
        label="Include Nearby Airports"
        checked={options.includeNearbyAirports}
        onChange={(checked) => update({ includeNearbyAirports: checked })}
      />
      {showFlexibleDates ? (
        <OptionToggle
          id="search-flexible-dates"
          label="Flexible Dates ±1 Day"
          checked={options.flexibleDates}
          onChange={(checked) => update({ flexibleDates: checked })}
        />
      ) : null}
    </div>
  );
}
