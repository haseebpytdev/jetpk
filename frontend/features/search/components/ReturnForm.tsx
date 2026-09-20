"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { useId } from "react";
import { AirportField, AirportSwapButton } from "./AirportField";
import { DateField } from "./DateField";
import { SearchOptionsBar } from "./SearchOptionsBar";
import { SearchFormErrors, type SearchLayout } from "./SearchFormErrors";
import type { Airport, SearchOptions } from "../types";

type ReturnFormProps = {
  origin: Airport | null;
  destination: Airport | null;
  departureDate: string;
  returnDate: string;
  options: SearchOptions;
  onOriginChange: (airport: Airport | null) => void;
  onDestinationChange: (airport: Airport | null) => void;
  onDepartureDateChange: (value: string) => void;
  onReturnDateChange: (value: string) => void;
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
  options,
  onOriginChange,
  onDestinationChange,
  onDepartureDateChange,
  onReturnDateChange,
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
        <div className="grid gap-2 sm:grid-cols-2 md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] md:items-end xl:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)_minmax(8.5rem,9.5rem)_minmax(8.5rem,9.5rem)_auto]">
          <AirportField id={`${id}-from`} label="From" value={origin} onChange={onOriginChange} density="compact" />
          <AirportSwapButton
            onSwap={() => {
              onOriginChange(destination);
              onDestinationChange(origin);
            }}
            className="justify-self-center md:mb-1"
          />
          <AirportField id={`${id}-to`} label="To" value={destination} onChange={onDestinationChange} density="compact" />
          {/*
            Below xl: balanced 2-col date row spanning the airport grid.
            At xl: display:contents so dates join the single desktop field row.
          */}
          <div className="col-span-full grid grid-cols-1 gap-2 sm:grid-cols-2 md:col-span-3 xl:contents">
            <DateField
              id={`${id}-departure`}
              label="Departure"
              value={departureDate}
              onChange={onDepartureDateChange}
              density="compact"
            />
            <DateField
              id={`${id}-return`}
              label="Return"
              value={returnDate}
              min={departureDate || undefined}
              onChange={onReturnDateChange}
              density="compact"
            />
          </div>
          <PrimaryButton
            type="submit"
            className="hidden w-full shrink-0 xl:mb-0.5 xl:inline-flex xl:w-auto xl:min-w-[9.5rem]"
            disabled={disabled}
          >
            {disabled ? "Searching…" : "Search Flights"}
          </PrimaryButton>
        </div>

        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between xl:block">
          <SearchOptionsBar options={options} onChange={onOptionsChange} compact className="max-lg:pr-0" />
          <PrimaryButton
            type="submit"
            className="w-full shrink-0 sm:ml-auto sm:w-auto sm:min-w-[9.5rem] xl:hidden"
            disabled={disabled}
          >
            {disabled ? "Searching…" : "Search Flights"}
          </PrimaryButton>
        </div>
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

      <div className="grid gap-3 sm:grid-cols-2">
        <DateField id={`${id}-departure`} label="Departure" value={departureDate} onChange={onDepartureDateChange} />
        <DateField
          id={`${id}-return`}
          label="Return"
          value={returnDate}
          min={departureDate || undefined}
          onChange={onReturnDateChange}
        />
      </div>

      <SearchOptionsBar options={options} onChange={onOptionsChange} />
      <SearchFormErrors errors={errors} />
      <PrimaryButton type="submit" className="w-full max-lg:w-[calc(100%-4.5rem)] sm:w-auto lg:w-auto" disabled={disabled}>
        {disabled ? "Searching…" : "Search Flights"}
      </PrimaryButton>
    </form>
  );
}
