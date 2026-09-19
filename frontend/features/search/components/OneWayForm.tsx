"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { useId } from "react";
import { AirportField, AirportSwapButton } from "./AirportField";
import { DateField } from "./DateField";
import { SearchOptionsBar } from "./SearchOptionsBar";
import { SearchFormErrors, type SearchLayout } from "./SearchFormErrors";
import type { Airport, SearchOptions } from "../types";

type OneWayFormProps = {
  origin: Airport | null;
  destination: Airport | null;
  departureDate: string;
  options: SearchOptions;
  onOriginChange: (airport: Airport | null) => void;
  onDestinationChange: (airport: Airport | null) => void;
  onDepartureDateChange: (value: string) => void;
  onOptionsChange: (options: SearchOptions) => void;
  onSubmit: () => void;
  errors: string[];
  disabled?: boolean;
  layout?: SearchLayout;
};

export function OneWayForm({
  origin,
  destination,
  departureDate,
  options,
  onOriginChange,
  onDestinationChange,
  onDepartureDateChange,
  onOptionsChange,
  onSubmit,
  errors,
  disabled = false,
  layout = "default",
}: OneWayFormProps) {
  const id = useId();
  const compact = layout === "compact";

  if (compact) {
    return (
      <form
        onSubmit={(event) => {
          event.preventDefault();
          onSubmit();
        }}
        className="space-y-3"
        aria-label="One way flight search"
      >
        <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-[minmax(0,1.1fr)_auto_minmax(0,1.1fr)_minmax(9rem,10rem)_auto] xl:items-end">
          <AirportField id={`${id}-from`} label="From" value={origin} onChange={onOriginChange} density="compact" />
          <AirportSwapButton
            onSwap={() => {
              onOriginChange(destination);
              onDestinationChange(origin);
            }}
            className="justify-self-center xl:mb-1"
          />
          <AirportField id={`${id}-to`} label="To" value={destination} onChange={onDestinationChange} density="compact" />
          <DateField
            id={`${id}-departure`}
            label="Departure"
            value={departureDate}
            onChange={onDepartureDateChange}
            density="compact"
          />
          <PrimaryButton type="submit" className="w-full shrink-0 max-lg:w-[calc(100%-4.5rem)] xl:mb-0.5 xl:w-auto xl:min-w-[9.5rem]" disabled={disabled}>
            {disabled ? "Searching…" : "Search Flights"}
          </PrimaryButton>
        </div>
        <SearchOptionsBar options={options} onChange={onOptionsChange} compact />
        <SearchFormErrors errors={errors} />
      </form>
    );
  }

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit();
      }}
      className="space-y-4"
      aria-label="One way flight search"
    >
      <div className="grid gap-3 md:grid-cols-[1fr_auto_1fr] md:items-end">
        <AirportField id={`${id}-from`} label="From" value={origin} onChange={onOriginChange} />
        <AirportSwapButton
          onSwap={() => {
            onOriginChange(destination);
            onDestinationChange(origin);
          }}
          className="hidden md:inline-flex"
        />
        <AirportField id={`${id}-to`} label="To" value={destination} onChange={onDestinationChange} />
      </div>

      <div className="grid gap-3 sm:grid-cols-2">
        <DateField id={`${id}-departure`} label="Departure" value={departureDate} onChange={onDepartureDateChange} />
      </div>

      <SearchOptionsBar options={options} onChange={onOptionsChange} />
      <SearchFormErrors errors={errors} />
      <PrimaryButton type="submit" className="w-full max-lg:w-[calc(100%-4.5rem)] sm:w-auto lg:w-auto" disabled={disabled}>
        {disabled ? "Searching…" : "Search Flights"}
      </PrimaryButton>
    </form>
  );
}
