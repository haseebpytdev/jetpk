"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { useId, useRef } from "react";
import { AirportField, AirportSwapButton, type AirportFieldHandle } from "./AirportField";
import { DateField, type DateFieldHandle } from "./DateField";
import { SearchOptionsBar } from "./SearchOptionsBar";
import { SearchFormErrors, type SearchLayout } from "./SearchFormErrors";
import { TravelersCabinSelector } from "./TravelersCabinSelector";
import type { Airport, PassengerSelection, SearchOptions } from "../types";

type OneWayFormProps = {
  origin: Airport | null;
  destination: Airport | null;
  departureDate: string;
  passengers: PassengerSelection;
  options: SearchOptions;
  onOriginChange: (airport: Airport | null) => void;
  onDestinationChange: (airport: Airport | null) => void;
  onDepartureDateChange: (value: string) => void;
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

export function OneWayForm({
  origin,
  destination,
  departureDate,
  passengers,
  options,
  onOriginChange,
  onDestinationChange,
  onDepartureDateChange,
  onPassengersChange,
  onOptionsChange,
  onSubmit,
  errors,
  disabled = false,
  layout = "default",
}: OneWayFormProps) {
  const id = useId();
  const compact = layout === "compact";
  const toRef = useRef<AirportFieldHandle>(null);
  const departureRef = useRef<DateFieldHandle>(null);

  const fromField = (
    <AirportField
      id={`${id}-from`}
      label="From"
      value={origin}
      onChange={onOriginChange}
      onSelectionComplete={() => toRef.current?.focusAndEdit()}
      density={compact ? "compact" : "default"}
    />
  );

  const toField = (
    <AirportField
      ref={toRef}
      id={`${id}-to`}
      label="To"
      value={destination}
      onChange={onDestinationChange}
      onSelectionComplete={() => departureRef.current?.focus()}
      density={compact ? "compact" : "default"}
    />
  );

  const departureField = (
    <DateField
      ref={departureRef}
      id={`${id}-departure`}
      label="Departure"
      value={departureDate}
      onChange={onDepartureDateChange}
      density={compact ? "compact" : "default"}
      className={compact ? "max-lg:col-span-2 lg:col-span-1" : undefined}
    />
  );

  const travelers = (
    <TravelersCabinSelector
      passengers={passengers}
      onAdultsChange={onPassengersChange.adults}
      onChildrenChange={onPassengersChange.children}
      onInfantsChange={onPassengersChange.infants}
      onCabinChange={onPassengersChange.cabin}
      density={compact ? "compact" : "default"}
      className={compact ? "max-lg:col-span-2 lg:col-span-1" : undefined}
    />
  );

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
        <div className="grid gap-2 lg:grid-cols-[minmax(0,1.1fr)_auto_minmax(0,1.1fr)_minmax(0,0.95fr)_minmax(0,1fr)_auto] lg:items-end">
          <div className="grid grid-cols-1 gap-2 max-sm:grid-cols-1 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] sm:items-end lg:contents">
            {fromField}
            <AirportSwapButton
              onSwap={() => {
                onOriginChange(destination);
                onDestinationChange(origin);
              }}
              className="justify-self-center sm:mb-1 lg:mb-1"
            />
            {toField}
          </div>
          {departureField}
          {travelers}
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
      aria-label="One way flight search"
    >
      <div className="grid gap-3 md:grid-cols-[1fr_auto_1fr] md:items-end">
        {fromField}
        <AirportSwapButton
          onSwap={() => {
            onOriginChange(destination);
            onDestinationChange(origin);
          }}
          className="hidden md:inline-flex"
        />
        {toField}
      </div>

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end">
        {departureField}
        {travelers}
        <PrimaryButton type="submit" className="w-full lg:w-auto" disabled={disabled}>
          {disabled ? "Searching…" : "Search Flights"}
        </PrimaryButton>
      </div>

      <SearchOptionsBar options={options} onChange={onOptionsChange} />
      <SearchFormErrors errors={errors} />
    </form>
  );
}
