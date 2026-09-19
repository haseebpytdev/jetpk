"use client";

import { cn } from "@/lib/cn";
import { useCallback, useRef, useState } from "react";
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
  type ProductTab,
  type SearchMode,
  type SearchOptions,
  type TripType,
} from "../types";
import { validateFlightSearch, validateGroupSearch } from "../utils/validation";
import { flattenLaravelFieldErrors } from "../utils/laravel-errors";
import { GroupTicketingForm } from "./GroupTicketingForm";
import { useGroupSearchFacets } from "@/features/group-ticketing/hooks/use-group-search-facets";
import { MultiCityForm } from "./MultiCityForm";
import { OneWayForm } from "./OneWayForm";
import { ReturnForm } from "./ReturnForm";
import { SearchStatusBanner } from "./SearchStatusBanner";
import { SearchServiceSwitcher } from "./SearchServiceSwitcher";
import { SearchTabs } from "./SearchTabs";
import { TravelersCabinSelector } from "./TravelersCabinSelector";
import type { SearchLayout } from "./SearchFormErrors";

function createSegment(id: string): FlightSegment {
  return { id, from: null, to: null, departureDate: "" };
}

const DEFAULT_OPTIONS: SearchOptions = {
  directFlightsOnly: false,
  includeNearbyAirports: false,
  flexibleDates: false,
};

function isTripType(mode: SearchMode): mode is TripType {
  return mode === "one_way" || mode === "return" || mode === "multi_city";
}

type SearchModuleProps = {
  className?: string;
  layout?: SearchLayout;
  /** When false, omit the Flights / Group service switcher (e.g. modify-search panels). */
  showServiceSwitcher?: boolean;
};

export function SearchModule({
  className,
  layout = "default",
  showServiceSwitcher = true,
}: SearchModuleProps) {
  const [mode, setMode] = useState<SearchMode>("one_way");
  const lastFlightModeRef = useRef<TripType>("one_way");
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
  const service: ProductTab = mode === "group" ? "group" : "flights";
  const tripMode: TripType = isTripType(mode) ? mode : lastFlightModeRef.current;
  const groupFacets = useGroupSearchFacets(mode === "group");
  const groupSectorValues = groupFacets.sectors.map((item) => item.value);
  const groupCategoryValues = groupFacets.categories.map((item) => item.value);

  const clearSubmitChrome = useCallback(() => {
    if (abortRef.current) {
      abortRef.current.abort();
      abortRef.current = null;
    }
    setErrors([]);
    setSubmitState({ status: "idle" });
  }, []);

  const handleTripTypeChange = useCallback(
    (next: TripType) => {
      clearSubmitChrome();
      lastFlightModeRef.current = next;
      setMode(next);
    },
    [clearSubmitChrome],
  );

  const handleServiceChange = useCallback(
    (next: ProductTab) => {
      clearSubmitChrome();
      if (next === "group") {
        if (isTripType(mode)) {
          lastFlightModeRef.current = mode;
        }
        setMode("group");
        return;
      }
      setMode(lastFlightModeRef.current);
    },
    [clearSubmitChrome, mode],
  );

  const submitToLaravel = useCallback(
    async (searchMode: Exclude<SearchMode, "group">, draftSegments: FlightSegment[], extraReturnDate?: string) => {
      const result = validateFlightSearch(searchMode, draftSegments, passengers, extraReturnDate);
      if (!result.valid) {
        setErrors(result.errors);
        setSubmitState({ status: "idle" });
        return;
      }

      setErrors([]);
      setSubmitState({ status: "submitting" });

      if (abortRef.current) abortRef.current.abort();
      const controller = new AbortController();
      abortRef.current = controller;

      const primary = draftSegments[0];
      if (!primary?.from || !primary.to) {
        setErrors(["Origin and destination are required."]);
        setSubmitState({ status: "idle" });
        return;
      }

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

  const travelersControl =
    service === "flights" ? (
      <TravelersCabinSelector
        passengers={passengers}
        onAdultsChange={setAdults}
        onChildrenChange={setChildren}
        onInfantsChange={setInfants}
        onCabinChange={setCabin}
        density={layout === "compact" ? "compact" : "default"}
      />
    ) : null;

  const searchCard = (
    <section
      className={cn(
        "min-w-0 w-full max-w-full overflow-x-clip overflow-y-visible rounded-jp-card border border-jp-border bg-jp-surface shadow-jp-card",
        layout === "compact" ? "p-jp-md sm:p-jp-lg" : "p-jp-lg sm:p-jp-xl",
        className,
      )}
      aria-label={service === "group" ? "Group ticketing search" : "Flight search"}
      data-testid="search-module"
      data-search-layout={layout}
      data-search-service={service}
      data-search-mode={mode}
    >
      {service === "flights" ? (
        <SearchTabs
          mode={tripMode}
          onModeChange={handleTripTypeChange}
          compact={layout === "compact"}
          end={travelersControl}
        />
      ) : null}

      <div
        className={cn(
          "transition-opacity duration-ui",
          service === "flights" ? "mt-jp-md" : "mt-0",
          layout === "compact" ? "min-h-0" : "min-h-[18rem]",
        )}
      >
        {mode === "one_way" ? (
          <OneWayForm
            origin={origin}
            destination={destination}
            departureDate={departureDate}
            options={options}
            onOriginChange={setOrigin}
            onDestinationChange={setDestination}
            onDepartureDateChange={setDepartureDate}
            onOptionsChange={setOptions}
            onSubmit={handleOneWaySubmit}
            errors={errors}
            disabled={isSubmitting}
            layout={layout}
          />
        ) : null}

        {mode === "return" ? (
          <ReturnForm
            origin={origin}
            destination={destination}
            departureDate={departureDate}
            returnDate={returnDate}
            options={options}
            onOriginChange={setOrigin}
            onDestinationChange={setDestination}
            onDepartureDateChange={setDepartureDate}
            onReturnDateChange={setReturnDate}
            onOptionsChange={setOptions}
            onSubmit={handleReturnSubmit}
            errors={errors}
            disabled={isSubmitting}
            layout={layout}
          />
        ) : null}

        {mode === "multi_city" ? (
          <MultiCityForm
            segments={segments}
            onSegmentChange={updateSegment}
            onAddSegment={addSegment}
            onRemoveSegment={removeSegment}
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

  if (!showServiceSwitcher) {
    return searchCard;
  }

  return (
    <div
      className="flex min-w-0 max-w-full flex-col gap-jp-sm lg:relative lg:flex-row lg:items-start lg:gap-jp-md"
      data-testid="homepage-search-shell"
    >
      {/* External rail: sits beside the card; on lg pulls slightly into the left gutter so the card keeps full container width. */}
      <SearchServiceSwitcher
        service={service}
        onServiceChange={handleServiceChange}
        className="self-start lg:sticky lg:top-[4.5rem] lg:-ml-16 lg:shrink-0"
      />
      <div className="min-w-0 w-full flex-1">{searchCard}</div>
    </div>
  );
}
