"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { useId } from "react";
import { AirportField, AirportSwapButton } from "./AirportField";
import { DateRangeField } from "./DateRangeField";
import { SearchOptionsBar } from "./SearchOptionsBar";
import { SearchFormErrors, type SearchLayout } from "./SearchFormErrors";
import { TravelersCabinSelector } from "./TravelersCabinSelector";
import type { Airport, PassengerSelection, SearchOptions } from "../types";

type ReturnFormProps = {
  origin: Airport | null;
  destination: Airport | null;
  departureDate: string;
  returnDate: string;
  passengers: PassengerSelection;
  options: SearchOptions;
  onOriginChange: (airport: Airport | null) => void;
  onDestinationChange: (airport: Airport | null) => void;
  onDepartureDateChange: (value: string) => void;
  onReturnDateChange: (value: string) => void;
  onPassengersChange: {
    adults: (value: number) => void;
    children: (value: number) => void;
    infants: (value: number) => void;
    cabin: (value: PassengerSelection["cabin"]) => void;
  };
  onOptionsChange: (options: SearchOptions) => void;
  onSubmit: () => void;
  errors: string[];
  disabled?: boolean;
  layout?: SearchLayout;
};

export function ReturnForm({
  origin,
  destination,
  departureDate,
  returnDate,
  passengers,
  options,
  onOriginChange,
  onDestinationChange,
  onDepartureDateChange,
  onReturnDateChange,
  onPassengersChange,
  onOptionsChange,
  onSubmit,
  errors,
  disabled = false,
  layout = "default",
}: ReturnFormProps) {
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
        aria-label="Round trip flight search"
      >
        <div className="grid gap-2 lg:grid-cols-[minmax(0,1.1fr)_auto_minmax(0,1.1fr)_minmax(0,1.05fr)_minmax(0,1fr)_auto] lg:items-end">
          <div className="grid grid-cols-1 gap-2 max-sm:grid-cols-1 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] sm:items-end lg:contents">
            <AirportField id={`${id}-from`} label="From" value={origin} onChange={onOriginChange} density="compact" />
            <AirportSwapButton
              onSwap={() => {
                onOriginChange(destination);
                onDestinationChange(origin);
              }}
              className="justify-self-center sm:mb-1 lg:mb-1"
            />
            <AirportField id={`${id}-to`} label="To" value={destination} onChange={onDestinationChange} density="compact" />
          </div>
          <DateRangeField
            id={`${id}-date-range`}
            departureDate={departureDate}
            returnDate={returnDate}
            onDepartureChange={onDepartureDateChange}
            onReturnChange={onReturnDateChange}
            density="compact"
            className="max-lg:col-span-2 lg:col-span-1"
          />
          <TravelersCabinSelector
            passengers={passengers}
            onAdultsChange={onPassengersChange.adults}
            onChildrenChange={onPassengersChange.children}
            onInfantsChange={onPassengersChange.infants}
            onCabinChange={onPassengersChange.cabin}
            density="compact"
            className="max-lg:col-span-2 lg:col-span-1"
          />
          <PrimaryButton type="submit" className="w-full shrink-0 max-lg:col-span-2 lg:col-span-1 lg:mb-0.5 lg:w-auto lg:min-w-[9.5rem]" disabled={disabled}>
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
      aria-label="Round trip flight search"
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

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_auto] lg:items-end">
        <DateRangeField
          id={`${id}-date-range`}
          departureDate={departureDate}
          returnDate={returnDate}
          onDepartureChange={onDepartureDateChange}
          onReturnChange={onReturnDateChange}
        />
        <TravelersCabinSelector
          passengers={passengers}
          onAdultsChange={onPassengersChange.adults}
          onChildrenChange={onPassengersChange.children}
          onInfantsChange={onPassengersChange.infants}
          onCabinChange={onPassengersChange.cabin}
        />
        <PrimaryButton type="submit" className="w-full lg:w-auto" disabled={disabled}>
          {disabled ? "Searching…" : "Search Flights"}
        </PrimaryButton>
      </div>

      <SearchOptionsBar options={options} onChange={onOptionsChange} />
      <SearchFormErrors errors={errors} />
    </form>
  );
}
