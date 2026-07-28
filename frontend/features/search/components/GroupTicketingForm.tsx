"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { useId } from "react";
import { GROUP_CATEGORY_FIXTURES, GROUP_DESTINATION_FIXTURES } from "../fixtures/group-categories";
import type { Airport, PassengerSelection } from "../types";
import { AirportField } from "./AirportField";
import { DateField } from "./DateField";
import { TravelersCabinSelector } from "./TravelersCabinSelector";

type GroupTicketingFormProps = {
  origin: Airport | null;
  destination: string;
  category: string;
  travelDate: string;
  passengers: PassengerSelection;
  onOriginChange: (airport: Airport | null) => void;
  onDestinationChange: (value: string) => void;
  onCategoryChange: (value: string) => void;
  onTravelDateChange: (value: string) => void;
  onPassengersChange: {
    adults: (value: number) => void;
    children: (value: number) => void;
    infants: (value: number) => void;
    cabin: (value: PassengerSelection["cabin"]) => void;
  };
  onSubmit: () => void;
  errors: string[];
  disabled?: boolean;
};

export function GroupTicketingForm({
  origin,
  destination,
  category,
  travelDate,
  passengers,
  onOriginChange,
  onDestinationChange,
  onCategoryChange,
  onTravelDateChange,
  onPassengersChange,
  onSubmit,
  errors,
  disabled = false,
}: GroupTicketingFormProps) {
  const id = useId();

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit();
      }}
      className="space-y-4"
      aria-label="Group ticketing search"
    >
      <div className="grid gap-3 sm:grid-cols-2">
        <AirportField id={`${id}-origin`} label="Origin" value={origin} onChange={onOriginChange} />
        <div>
          <label htmlFor={`${id}-destination`} className="mb-1 block text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">
            Destination / Sector
          </label>
          <select
            id={`${id}-destination`}
            value={destination}
            onChange={(event) => onDestinationChange(event.target.value)}
            className="w-full min-h-jp-tap rounded-jp-md border border-jp-border bg-jp-surface px-3 py-2.5 text-jp-sm focus-visible:outline-none focus-visible:shadow-jp-focus"
          >
            <option value="">Select sector</option>
            {GROUP_DESTINATION_FIXTURES.map((item) => (
              <option key={item} value={item}>
                {item}
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <DateField id={`${id}-date`} label="Travel date" value={travelDate} onChange={onTravelDateChange} />
        <div>
          <span className="mb-1 block text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">Category</span>
          <div role="radiogroup" aria-label="Group category" className="flex flex-wrap gap-2">
            {GROUP_CATEGORY_FIXTURES.map((item) => (
              <label
                key={item.slug}
                className="inline-flex cursor-pointer items-center gap-2 rounded-jp-pill border border-jp-border px-3 py-1.5 text-jp-sm has-[:checked]:border-jp-primary has-[:checked]:bg-jp-primary-soft"
              >
                <input
                  type="radio"
                  name={`${id}-category`}
                  value={item.slug}
                  checked={category === item.slug}
                  onChange={() => onCategoryChange(item.slug)}
                  className="text-jp-primary focus-visible:outline-none focus-visible:shadow-jp-focus"
                />
                <span>{item.label}</span>
              </label>
            ))}
          </div>
        </div>
        <TravelersCabinSelector
          passengers={passengers}
          onAdultsChange={onPassengersChange.adults}
          onChildrenChange={onPassengersChange.children}
          onInfantsChange={onPassengersChange.infants}
          onCabinChange={onPassengersChange.cabin}
        />
      </div>

      {errors.length > 0 ? (
        <div role="status" aria-live="polite" className="rounded-jp-md border border-red-200 bg-red-50 px-3 py-2 text-jp-sm text-red-800">
          <ul className="list-disc pl-4">
            {errors.map((error) => (
              <li key={error}>{error}</li>
            ))}
          </ul>
        </div>
      ) : null}

      <PrimaryButton type="submit" className="w-full sm:w-auto" disabled={disabled}>
        {disabled ? "Searching…" : "Search Group Fares"}
      </PrimaryButton>
    </form>
  );
}
