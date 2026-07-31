"use client";

import { cn } from "@/lib/cn";
import { useCallback, useMemo, useRef, useState } from "react";
import {
  handoffToGroupSearch,
  handoffToFlightResults,
  initFlightSearch,
  buildGroupHandoffQuery,
  type SearchSubmitState,
} from "@/services/flight-search";
import { findAirportByIata } from "../utils/airport-filter";
import { usePassengerSelection } from "../hooks/use-passenger-selection";
import {
  MULTI_CITY_MAX_SEGMENTS,
  MULTI_CITY_MIN_SEGMENTS,
  type FlightSegment,
  type SearchMode,
  type SearchOptions,
} from "../types";
import { validateFlightSearch, validateGroupSearch } from "../utils/validation";
import { flattenLaravelFieldErrors } from "../utils/laravel-errors";
import { GroupTicketingForm } from "./GroupTicketingForm";
import { useGroupSearchFacets } from "@/features/group-ticketing/hooks/use-group-search-facets";
import { MultiCityForm } from "./MultiCityForm";
import { OneWayForm } from "./OneWayForm";
import { ReturnForm } from "./ReturnForm";
import { SearchStatusBanner } from "./SearchStatusBanner";
import { SearchTabs } from "./SearchTabs";
import type { SearchLayout } from "./SearchFormErrors";

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
  layout?: SearchLayout;
  variant?: "default" | "blueprint";
};

export function SearchModule({ className, layout = "default", variant = "default" }: SearchModuleProps) {
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
  const [groupSector, setGroupSector] = useState("");
  const [groupCategory, setGroupCategory] = useState("all");
  const [groupTravelDate, setGroupTravelDate] = useState("");
  const [errors, setErrors] = useState<string[]>([]);
  const [submitState, setSubmitState] = useState<SearchSubmitState>({ status: "idle" });
  const abortRef = useRef<AbortController | null>(null);

  const {
    passengers,
    setAdults,
    setChildren,
    setInfants,
    setCabin,
  } = usePassengerSelection();

  const isSubmitting = submitState.status === "submitting" || submitState.status === "redirecting";
  const groupFacets = useGroupSearchFacets(mode === "group");
  const groupSectorValues = groupFacets.sectors.map((item) => item.value);
  const groupCategoryValues = groupFacets.categories.map((item) => item.value);

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
    if (abortRef.current) {
      abortRef.current.abort();
      abortRef.current = null;
    }
    setMode(next);
    setErrors([]);
    setSubmitState({ status: "idle" });
  }, []);

  const submitToLaravel = useCallback(
    async (searchMode: Exclude<SearchMode, "group">, draftSegments: FlightSegment[], extraReturnDate?: string) => {
      const result = validateFlightSearch(searchMode, draftSegments, passengers, extraReturnDate);
      if (!result.valid) {
        setErrors(result.errors);
        setSubmitState({ status: "idle" });
        return;
      }

      const primary = draftSegments[0];
      if (!primary?.from || !primary?.to) {
        setErrors(["Origin and destination are required."]);
        setSubmitState({ status: "idle" });
        return;
      }

      setErrors([]);
      setSubmitState({ status: "submitting" });

      if (abortRef.current) abortRef.current.abort();
      const controller = new AbortController();
      abortRef.current = controller;

      const response = await initFlightSearch(
        {
          mode: searchMode,
          origin: primary.from.iata,
          destination: primary.to.iata,
          departureDate: primary.departureDate,
          returnDate: extraReturnDate,
          segments: searchMode === "multi_city" ? draftSegments : undefined,
          passengers,
          options,
        },
        controller.signal,
      );

      if (!response.ok) {
        const laravelErrors = flattenLaravelFieldErrors(response.fieldErrors);
        setErrors(laravelErrors.length > 0 ? laravelErrors : [response.message]);
        setSubmitState({
          status: "error",
          message: response.message,
          fieldErrors: response.fieldErrors,
        });
        return;
      }

      setSubmitState({ status: "redirecting", targetUrl: response.resultsPath });
      handoffToFlightResults(response.resultsPath);
    },
    [options, passengers],
  );

  const handleOneWaySubmit = () => {
    void submitToLaravel("one_way", [{ id: "one-way", from: origin, to: destination, departureDate }]);
  };

  const handleReturnSubmit = () => {
    void submitToLaravel(
      "return",
      [{ id: "outbound", from: origin, to: destination, departureDate }],
      returnDate,
    );
  };

  const handleMultiCitySubmit = () => {
    void submitToLaravel("multi_city", segments);
  };

  const handleGroupSubmit = () => {
    const draftInput = {
      sector: groupSector,
      category: groupCategory,
      travelDate: groupTravelDate,
    };
    const result = validateGroupSearch(draftInput, {
      sectorValues: groupSectorValues,
      categoryValues: groupCategoryValues,
    });
    if (!result.valid) {
      setErrors(result.errors);
      setSubmitState({ status: "idle" });
      return;
    }

    setErrors([]);
    setSubmitState({ status: "redirecting", targetUrl: "/groups/search" });
    handoffToGroupSearch(buildGroupHandoffQuery(draftInput));
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
        variant === "blueprint"
          ? "relative mx-auto w-full min-h-[140px] max-w-[960px] overflow-x-hidden overflow-y-visible rounded-[1.25rem] border border-jp-border bg-jp-surface pt-9 shadow-[0_20px_50px_-20px_rgba(20,50,75,0.35)]"
          : "rounded-jp-card border border-jp-border bg-jp-surface shadow-jp-card",
        variant !== "blueprint" && (layout === "compact" ? "p-jp-md sm:p-jp-lg" : "p-jp-lg sm:p-jp-xl"),
        className,
      )}
      aria-label="Flight search"
      data-testid="search-module"
      data-search-layout={variant === "blueprint" ? "blueprint" : layout}
      data-search-variant={variant}
    >
      {variant === "blueprint" ? (
        <div className="absolute left-4 top-0 z-10 w-[360px] -translate-y-2">
          <SearchTabs
            mode={mode}
            onModeChange={handleModeChange}
            compact={layout === "compact" || variant === "blueprint"}
            variant={variant}
          />
        </div>
      ) : (
        <div>
          <SearchTabs
            mode={mode}
            onModeChange={handleModeChange}
            compact={layout === "compact"}
            variant={variant}
          />
        </div>
      )}

      <div
        className={cn(
          "transition-opacity duration-ui",
          variant === "blueprint" ? "px-3 pb-3 pt-2" : "mt-jp-md",
          layout === "compact" && variant !== "blueprint" ? "min-h-0" : variant === "blueprint" ? "min-h-0" : "min-h-[18rem]",
        )}
      >
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
            disabled={isSubmitting}
            layout={variant === "blueprint" ? "blueprint" : layout}
            variant={variant}
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
            disabled={isSubmitting}
            layout={variant === "blueprint" ? "blueprint" : layout}
            variant={variant}
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
            disabled={isSubmitting}
          />
        ) : null}

        {mode === "group" ? (
          <GroupTicketingForm
            sector={groupSector}
            category={groupCategory}
            travelDate={groupTravelDate}
            facetsState={groupFacets.state}
            sectors={groupFacets.sectors}
            categories={groupFacets.categories}
            dateBounds={groupFacets.dateBounds}
            facetsError={groupFacets.errorMessage}
            onRetryFacets={groupFacets.retry}
            onSectorChange={setGroupSector}
            onCategoryChange={setGroupCategory}
            onTravelDateChange={setGroupTravelDate}
            onSubmit={handleGroupSubmit}
            errors={errors}
            disabled={isSubmitting}
          />
        ) : null}
      </div>

      <SearchStatusBanner state={submitState} />
    </section>
  );
}
