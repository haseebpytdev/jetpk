"use client";

import { cn } from "@/lib/cn";
import { useCallback, useMemo, useState } from "react";
import { findAirportByIata } from "../utils/airport-filter";
import { usePassengerSelection } from "../hooks/use-passenger-selection";
import {
  MULTI_CITY_MAX_SEGMENTS,
  MULTI_CITY_MIN_SEGMENTS,
  type FlightSegment,
  type GroupSearchDraft,
  type SearchDraft,
  type SearchMode,
  type SearchOptions,
} from "../types";
import {
  buildGroupSearchDraft,
  buildSearchDraft,
  validateFlightSearch,
  validateGroupSearch,
} from "../utils/validation";
import { GroupTicketingForm } from "./GroupTicketingForm";
import { MultiCityForm } from "./MultiCityForm";
import { OneWayForm } from "./OneWayForm";
import { ReturnForm } from "./ReturnForm";
import { SearchSubmitPreview } from "./SearchSubmitPreview";
import { SearchTabs } from "./SearchTabs";

function createSegment(id: string): FlightSegment {
  return { id, from: null, to: null, departureDate: "" };
}

const DEFAULT_OPTIONS: SearchOptions = {
  directFlightsOnly: false,
  includeNearbyAirports: false,
  flexibleDates: false,
};

type SearchModuleProps = {
  className?: string;
};

export function SearchModule({ className }: SearchModuleProps) {
  const [mode, setMode] = useState<SearchMode>("one_way");
  const [origin, setOrigin] = useState(() => findAirportByIata("ISB") ?? null);
  const [destination, setDestination] = useState(() => findAirportByIata("DXB") ?? null);
  const [departureDate, setDepartureDate] = useState("");
  const [returnDate, setReturnDate] = useState("");
  const [options, setOptions] = useState<SearchOptions>(DEFAULT_OPTIONS);
  const [segments, setSegments] = useState<FlightSegment[]>([
    createSegment("segment-1"),
    createSegment("segment-2"),
  ]);
  const [groupDestination, setGroupDestination] = useState("");
  const [groupCategory, setGroupCategory] = useState("all");
  const [groupTravelDate, setGroupTravelDate] = useState("");
  const [errors, setErrors] = useState<string[]>([]);
  const [preview, setPreview] = useState<SearchDraft | GroupSearchDraft | null>(null);

  const {
    passengers,
    setAdults,
    setChildren,
    setInfants,
    setCabin,
  } = usePassengerSelection();

  const passengerHandlers = useMemo(
    () => ({
      adults: setAdults,
      children: setChildren,
      infants: setInfants,
      cabin: setCabin,
    }),
    [setAdults, setChildren, setInfants, setCabin],
  );

  const handleModeChange = useCallback((next: SearchMode) => {
    setMode(next);
    setErrors([]);
    setPreview(null);
  }, []);

  const submitFlightSearch = useCallback(
    (searchMode: Exclude<SearchMode, "group">, draftSegments: FlightSegment[], extraReturnDate?: string) => {
      const result = validateFlightSearch(searchMode, draftSegments, passengers, extraReturnDate);
      if (!result.valid) {
        setErrors(result.errors);
        setPreview(null);
        return;
      }
      setErrors([]);
      const draft = buildSearchDraft(searchMode, draftSegments, passengers, options);
      setPreview(draft);
      if (process.env.NODE_ENV === "development") {
        console.info("[SearchDraft]", draft);
      }
    },
    [options, passengers],
  );

  const handleOneWaySubmit = () => {
    submitFlightSearch("one_way", [
      { id: "one-way", from: origin, to: destination, departureDate },
    ]);
  };

  const handleReturnSubmit = () => {
    submitFlightSearch(
      "return",
      [{ id: "outbound", from: origin, to: destination, departureDate }],
      returnDate,
    );
  };

  const handleMultiCitySubmit = () => {
    submitFlightSearch("multi_city", segments);
  };

  const handleGroupSubmit = () => {
    const draftInput = {
      origin,
      destination: groupDestination,
      category: groupCategory,
      travelDate: groupTravelDate,
      passengers,
    };
    const result = validateGroupSearch(draftInput);
    if (!result.valid) {
      setErrors(result.errors);
      setPreview(null);
      return;
    }
    setErrors([]);
    const draft = buildGroupSearchDraft(draftInput);
    setPreview(draft);
    if (process.env.NODE_ENV === "development") {
      console.info("[GroupSearchDraft]", draft);
    }
  };

  const updateSegment = (index: number, segment: FlightSegment) => {
    setSegments((current) => current.map((item, itemIndex) => (itemIndex === index ? segment : item)));
  };

  const addSegment = () => {
    setSegments((current) => {
      if (current.length >= MULTI_CITY_MAX_SEGMENTS) return current;
      return [...current, createSegment(`segment-${current.length + 1}`)];
    });
  };

  const removeSegment = (index: number) => {
    setSegments((current) => {
      if (current.length <= MULTI_CITY_MIN_SEGMENTS) return current;
      return current.filter((_, itemIndex) => itemIndex !== index);
    });
  };

  return (
    <section
      className={cn(
        "rounded-jp-card border border-jp-border bg-jp-surface p-jp-lg shadow-jp-card sm:p-jp-xl",
        className,
      )}
      aria-label="Flight search"
      data-testid="search-module"
    >
      <SearchTabs mode={mode} onModeChange={handleModeChange} />

      <div className="mt-jp-md min-h-[18rem] transition-opacity duration-ui">
        {mode === "one_way" ? (
          <OneWayForm
            origin={origin}
            destination={destination}
            departureDate={departureDate}
            passengers={passengers}
            options={options}
            onOriginChange={setOrigin}
            onDestinationChange={setDestination}
            onDepartureDateChange={setDepartureDate}
            onPassengersChange={passengerHandlers}
            onOptionsChange={setOptions}
            onSubmit={handleOneWaySubmit}
            errors={errors}
          />
        ) : null}

        {mode === "return" ? (
          <ReturnForm
            origin={origin}
            destination={destination}
            departureDate={departureDate}
            returnDate={returnDate}
            passengers={passengers}
            options={options}
            onOriginChange={setOrigin}
            onDestinationChange={setDestination}
            onDepartureDateChange={setDepartureDate}
            onReturnDateChange={setReturnDate}
            onPassengersChange={passengerHandlers}
            onOptionsChange={setOptions}
            onSubmit={handleReturnSubmit}
            errors={errors}
          />
        ) : null}

        {mode === "multi_city" ? (
          <MultiCityForm
            segments={segments}
            passengers={passengers}
            onSegmentChange={updateSegment}
            onAddSegment={addSegment}
            onRemoveSegment={removeSegment}
            onPassengersChange={passengerHandlers}
            onSubmit={handleMultiCitySubmit}
            errors={errors}
          />
        ) : null}

        {mode === "group" ? (
          <GroupTicketingForm
            origin={origin}
            destination={groupDestination}
            category={groupCategory}
            travelDate={groupTravelDate}
            passengers={passengers}
            onOriginChange={setOrigin}
            onDestinationChange={setGroupDestination}
            onCategoryChange={setGroupCategory}
            onTravelDateChange={setGroupTravelDate}
            onPassengersChange={passengerHandlers}
            onSubmit={handleGroupSubmit}
            errors={errors}
          />
        ) : null}
      </div>

      <SearchSubmitPreview draft={preview} />
    </section>
  );
}
