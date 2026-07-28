"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { useId } from "react";
import { AirportField, AirportSwapButton } from "./AirportField";
import { DateField } from "./DateField";
import { SearchOptionsBar } from "./SearchOptionsBar";
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
}: ReturnFormProps) {
  const id = useId();

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
        <DateField
          id={`${id}-departure`}
          label="Departure"
          value={departureDate}
          onChange={onDepartureDateChange}
        />
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

      {errors.length > 0 ? (
        <div role="alert" className="rounded-jp-md border border-red-200 bg-red-50 px-3 py-2 text-jp-sm text-red-800">
          <ul className="list-disc pl-4">
            {errors.map((error) => (
              <li key={error}>{error}</li>
            ))}
          </ul>
        </div>
      ) : null}

      <PrimaryButton type="submit" className="w-full sm:w-auto">
        Search Flights
      </PrimaryButton>
    </form>
  );
}
