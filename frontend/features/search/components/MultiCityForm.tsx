"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { SecondaryButton } from "@/components/ui/SecondaryButton";
import { useId } from "react";
import { MULTI_CITY_MAX_SEGMENTS, MULTI_CITY_MIN_SEGMENTS } from "../types";
import type { FlightSegment } from "../types";
import { AirportField } from "./AirportField";
import { DateField } from "./DateField";

type MultiCityFormProps = {
  segments: FlightSegment[];
  onSegmentChange: (index: number, segment: FlightSegment) => void;
  onAddSegment: () => void;
  onRemoveSegment: (index: number) => void;
  onSubmit: () => void;
  errors: string[];
  disabled?: boolean;
};

export function MultiCityForm({
  segments,
  onSegmentChange,
  onAddSegment,
  onRemoveSegment,
  onSubmit,
  errors,
  disabled = false,
}: MultiCityFormProps) {
  const id = useId();

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit();
      }}
      className="space-y-4"
      aria-label="Multi-city flight search"
    >
      <p className="text-jp-xs text-jp-muted">
        Connected to the existing Laravel multi-city search contract (up to {MULTI_CITY_MAX_SEGMENTS} segments).
      </p>

      <div className="space-y-4">
        {segments.map((segment, index) => (
          <fieldset
            key={segment.id}
            className="rounded-jp-md border border-jp-border-soft bg-jp-surface-muted/60 p-3"
          >
            <legend className="px-1 text-jp-sm font-semibold text-jp-text">Flight {index + 1}</legend>
            <div className="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <AirportField
                id={`${id}-from-${index}`}
                label="From"
                value={segment.from}
                onChange={(airport) => onSegmentChange(index, { ...segment, from: airport })}
              />
              <AirportField
                id={`${id}-to-${index}`}
                label="To"
                value={segment.to}
                onChange={(airport) => onSegmentChange(index, { ...segment, to: airport })}
              />
              <DateField
                id={`${id}-date-${index}`}
                label="Departure"
                value={segment.departureDate}
                onChange={(value) => onSegmentChange(index, { ...segment, departureDate: value })}
              />
            </div>
            {segments.length > MULTI_CITY_MIN_SEGMENTS ? (
              <div className="mt-3">
                <SecondaryButton type="button" onClick={() => onRemoveSegment(index)}>
                  Remove Flight
                </SecondaryButton>
              </div>
            ) : null}
          </fieldset>
        ))}
      </div>

      {segments.length < MULTI_CITY_MAX_SEGMENTS ? (
        <SecondaryButton type="button" onClick={onAddSegment}>
          Add Flight
        </SecondaryButton>
      ) : null}

      {errors.length > 0 ? (
        <div role="status" aria-live="polite" className="rounded-jp-md border border-red-200 bg-red-50 px-3 py-2 text-jp-sm text-red-800">
          <ul className="list-disc pl-4">
            {errors.map((error) => (
              <li key={error}>{error}</li>
            ))}
          </ul>
        </div>
      ) : null}

      <PrimaryButton type="submit" className="w-full max-lg:w-[calc(100%-4.5rem)] sm:w-auto lg:w-auto" disabled={disabled}>
        {disabled ? "Searching…" : "Search Flights"}
      </PrimaryButton>
    </form>
  );
}
