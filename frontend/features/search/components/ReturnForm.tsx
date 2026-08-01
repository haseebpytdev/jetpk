"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { cn } from "@/lib/cn";
import { useId } from "react";
import { AirportField, AirportSwapButton } from "./AirportField";
import { BlueprintFieldSegment, BlueprintSearchRow } from "./blueprint/BlueprintFieldSegment";
import { DateField } from "./DateField";
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
  variant?: "default" | "blueprint";
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
  variant = "default",
}: ReturnFormProps) {
  const id = useId();
  const compact = layout === "compact";
  const blueprint = layout === "blueprint" || variant === "blueprint";

  if (blueprint) {
    return (
      <form
        onSubmit={(event) => {
          event.preventDefault();
          onSubmit();
        }}
        className="space-y-3"
        aria-label="Round trip flight search"
      >
        <BlueprintSearchRow>
          <BlueprintFieldSegment widthClass="flex-[170_1_0]">
            <AirportField id={`${id}-from`} label="From" value={origin} onChange={onOriginChange} variant="blueprint" />
          </BlueprintFieldSegment>
          <BlueprintFieldSegment widthClass="w-8 shrink-0 flex items-center justify-center px-0">
            <AirportSwapButton
              variant="blueprint"
              onSwap={() => {
                onOriginChange(destination);
                onDestinationChange(origin);
              }}
            />
          </BlueprintFieldSegment>
          <BlueprintFieldSegment widthClass="flex-[170_1_0]">
            <AirportField id={`${id}-to`} label="To" value={destination} onChange={onDestinationChange} variant="blueprint" />
          </BlueprintFieldSegment>
          <BlueprintFieldSegment widthClass="flex-[120_1_0]">
            <DateField id={`${id}-departure`} label="Departure" value={departureDate} onChange={onDepartureDateChange} variant="blueprint" />
          </BlueprintFieldSegment>
          <BlueprintFieldSegment widthClass="flex-[120_1_0]">
            <DateField id={`${id}-return`} label="Return" value={returnDate} min={departureDate || undefined} onChange={onReturnDateChange} variant="blueprint" />
          </BlueprintFieldSegment>
          <BlueprintFieldSegment widthClass="flex-[170_1_0]">
            <TravelersCabinSelector
              passengers={passengers}
              onAdultsChange={onPassengersChange.adults}
              onChildrenChange={onPassengersChange.children}
              onInfantsChange={onPassengersChange.infants}
              onCabinChange={onPassengersChange.cabin}
              variant="blueprint"
            />
          </BlueprintFieldSegment>
          <BlueprintFieldSegment widthClass="w-[108px] shrink-0 flex items-end pb-1.5">
            <PrimaryButton type="submit" aria-label="Search Flights" className="h-11 w-full shrink-0 px-2 text-jp-xs font-semibold" disabled={disabled}>
              {disabled ? "Searching…" : "Search Flights"}
            </PrimaryButton>
          </BlueprintFieldSegment>
        </BlueprintSearchRow>
        <div className="space-y-3 lg:hidden">
          <AirportField id={`${id}-from-m`} label="From" value={origin} onChange={onOriginChange} density="compact" />
          <AirportSwapButton onSwap={() => { onOriginChange(destination); onDestinationChange(origin); }} className="mx-auto" />
          <AirportField id={`${id}-to-m`} label="To" value={destination} onChange={onDestinationChange} density="compact" />
          <DateField id={`${id}-departure-m`} label="Departure" value={departureDate} onChange={onDepartureDateChange} density="compact" />
          <DateField id={`${id}-return-m`} label="Return" value={returnDate} min={departureDate || undefined} onChange={onReturnDateChange} density="compact" />
          <TravelersCabinSelector passengers={passengers} onAdultsChange={onPassengersChange.adults} onChildrenChange={onPassengersChange.children} onInfantsChange={onPassengersChange.infants} onCabinChange={onPassengersChange.cabin} density="compact" />
          <PrimaryButton type="submit" className="w-full" disabled={disabled}>{disabled ? "Searching…" : "Search Flights"}</PrimaryButton>
        </div>
        <SearchOptionsBar options={options} onChange={onOptionsChange} compact className="lg:hidden" />
        <SearchFormErrors errors={errors} />
      </form>
    );
  }

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
        <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)_minmax(8.5rem,9.5rem)_minmax(8.5rem,9.5rem)_minmax(10rem,12rem)_auto] xl:items-end">
          <AirportField id={`${id}-from`} label="From" value={origin} onChange={onOriginChange} density="compact" />
          <AirportSwapButton
            onSwap={() => {
              onOriginChange(destination);
              onDestinationChange(origin);
            }}
            className="justify-self-center xl:mb-1"
          />
          <AirportField id={`${id}-to`} label="To" value={destination} onChange={onDestinationChange} density="compact" />
          <DateField id={`${id}-departure`} label="Departure" value={departureDate} onChange={onDepartureDateChange} density="compact" />
          <DateField
            id={`${id}-return`}
            label="Return"
            value={returnDate}
            min={departureDate || undefined}
            onChange={onReturnDateChange}
            density="compact"
          />
          <TravelersCabinSelector
            passengers={passengers}
            onAdultsChange={onPassengersChange.adults}
            onChildrenChange={onPassengersChange.children}
            onInfantsChange={onPassengersChange.infants}
            onCabinChange={onPassengersChange.cabin}
            density="compact"
            className="sm:col-span-2 xl:col-span-1"
          />
          <PrimaryButton type="submit" className="w-full shrink-0 xl:mb-0.5 xl:w-auto xl:min-w-[9.5rem]" disabled={disabled}>
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

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <DateField id={`${id}-departure`} label="Departure" value={departureDate} onChange={onDepartureDateChange} />
        <DateField
          id={`${id}-return`}
          label="Return"
          value={returnDate}
          min={departureDate || undefined}
          onChange={onReturnDateChange}
        />
        <TravelersCabinSelector
          passengers={passengers}
          onAdultsChange={onPassengersChange.adults}
          onChildrenChange={onPassengersChange.children}
          onInfantsChange={onPassengersChange.infants}
          onCabinChange={onPassengersChange.cabin}
          className="sm:col-span-2 lg:col-span-2"
        />
      </div>

      <SearchOptionsBar options={options} onChange={onOptionsChange} />
      <SearchFormErrors errors={errors} />
      <PrimaryButton type="submit" className="w-full sm:w-auto" disabled={disabled}>
        {disabled ? "Searching…" : "Search Flights"}
      </PrimaryButton>
    </form>
  );
}
