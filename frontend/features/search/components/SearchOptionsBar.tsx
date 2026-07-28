"use client";

import { cn } from "@/lib/cn";
import type { SearchOptions } from "../types";

type SearchOptionsBarProps = {
  options: SearchOptions;
  onChange: (options: SearchOptions) => void;
  showFlexibleDates?: boolean;
  className?: string;
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
    <label htmlFor={id} className="inline-flex cursor-pointer items-center gap-2 text-jp-sm text-jp-text">
      <input
        id={id}
        type="checkbox"
        checked={checked}
        onChange={(event) => onChange(event.target.checked)}
        className="h-4 w-4 rounded border-jp-border text-jp-primary focus-visible:outline-none focus-visible:shadow-jp-focus"
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
}: SearchOptionsBarProps) {
  const update = (patch: Partial<SearchOptions>) => onChange({ ...options, ...patch });

  return (
    <div className={cn("flex flex-wrap gap-x-5 gap-y-2", className)}>
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
