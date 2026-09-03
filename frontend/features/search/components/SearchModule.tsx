"use client";

import { cn } from "@/lib/cn";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import {
  handoffToGroupSearch,
  buildGroupHandoffQuery,
  initFlightSearch,
  type SearchSubmitState,
} from "@/services/flight-search";
import { buildFlightResultsPagePath, buildFlightSearchQueryParams } from "../utils/laravel-payload";
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
import { SharedGroupSearch } from "@/features/group-ticketing/components/SharedGroupSearch";
import { useGroupSearchFacets } from "@/features/group-ticketing/hooks/use-group-search-facets";
import { MultiCityForm } from "./MultiCityForm";
import { OneWayForm } from "./OneWayForm";
import { ReturnForm } from "./ReturnForm";
import { SearchStatusBanner } from "./SearchStatusBanner";
import { ProductSearchTabs } from "./ProductSearchTabs";
import { TripTypeDropdown } from "./TripTypeDropdown";
import type { SearchLayout } from "./SearchFormErrors";

function createSegment(id: string): FlightSegment {
  return { id, from: null, to: null, departureDate: "" };
}

function resolveSearchMode(productTab: ProductTab, tripType: TripType): SearchMode {
  return productTab === "group" ? "group" : tripType;
}

type SearchModuleProps = {
  className?: string;
  layout?: SearchLayout;
  variant?: "home" | "results";
  initialParams?: URLSearchParams | null;
  onSubmitted?: () => void;
};

function hydrateTripType(params?: URLSearchParams | null): TripType {
  const tripType = params?.get("trip_type");
  if (tripType === "round_trip") return "return";
  if (tripType === "multi_city") return "multi_city";
  return "one_way";
}

export function SearchModule({
  className,
  layout = "default",
  variant = "home",
  initialParams = null,
  onSubmitted,
}: SearchModuleProps) {
  const router = useRouter();

  // Warm the results route so soft navigation after search init pays less shell cost.
  useEffect(() => {
    try {
      router.prefetch("/flights/results");
    } catch {
      /* prefetch is best-effort */
    }
  }, [router]);

  const [productTab, setProductTab] = useState<ProductTab>("flights");
  const [tripType, setTripType] = useState<TripType>(() => hydrateTripType(initialParams));
  const mode = resolveSearchMode(productTab, tripType);
  const [origin, setOrigin] = useState(() => {
    const from = initialParams?.get("from");
    if (from) return findAirportByIata(from) ?? null;
    return variant === "results" ? null : findAirportByIata("ISB") ?? null;
  });
  const [destination, setDestination] = useState(() => {
    const to = initialParams?.get("to");
    if (to) return findAirportByIata(to) ?? null;
    return variant === "results" ? null : findAirportByIata("DXB") ?? null;
  });
  const [departureDate, setDepartureDate] = useState(() => initialParams?.get("depart") ?? "");
  const [returnDate, setReturnDate] = useState(() => initialParams?.get("return_date") ?? "");
  const [options, setOptions] = useState<SearchOptions>(() => ({
    directFlightsOnly: initialParams?.get("stops") === "direct",
    includeNearbyAirports: initialParams?.get("include_nearby") === "1",
    flexibleDates: initialParams?.get("flexible_dates") === "1",
  }));
  const [segments, setSegments] = useState<FlightSegment[]>(() => {
    const fromList = initialParams?.getAll("multi_from[]") ?? [];
    if (fromList.length >= 2) {
      return fromList.map((code, index) => ({
        id: `segment-${index + 1}`,
        from: findAirportByIata(code) ?? null,
        to: findAirportByIata(initialParams?.getAll("multi_to[]")[index] ?? "") ?? null,
        departureDate: initialParams?.getAll("multi_depart[]")[index] ?? "",
      }));
    }
    return [createSegment("segment-1"), createSegment("segment-2")];
  });
  const [groupAirline, setGroupAirline] = useState("");
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
  } = usePassengerSelection({
    adults: Number(initialParams?.get("adults") ?? "1"),
    children: Number(initialParams?.get("children") ?? "0"),
    infants: Number(initialParams?.get("infants") ?? "0"),
    cabin: (initialParams?.get("cabin") ?? "economy") as import("../types").CabinClass,
  });

  const isSubmitting = submitState.status === "submitting" || submitState.status === "redirecting";
  const groupFacets = useGroupSearchFacets(mode === "group");
  const groupAirlineValues = groupFacets.airlines.map((item) => item.value);
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

  const resetSubmitState = useCallback(() => {
    if (abortRef.current) {
      abortRef.current.abort();
      abortRef.current = null;
    }
    setErrors([]);
    setSubmitState({ status: "idle" });
  }, []);

  const handleProductTabChange = useCallback(
    (next: ProductTab) => {
      resetSubmitState();
      setProductTab(next);
    },
    [resetSubmitState],
  );

  const handleTripTypeChange = useCallback(
    (next: TripType) => {
      resetSubmitState();
      setTripType(next);
    },
    [resetSubmitState],
  );

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

      const payload = {
        mode: searchMode,
        origin: primary.from.iata,
        destination: primary.to.iata,
        departureDate: primary.departureDate,
        returnDate: extraReturnDate,
        segments: searchMode === "multi_city" ? draftSegments : undefined,
        passengers,
        options,
      };
      const query = buildFlightSearchQueryParams(payload);

      // JP-DEEP-CLOSURE-01: start progressive search before results navigation so
      // supplier network overlaps the results shell instead of waiting for mount.
      let resultsPath = buildFlightResultsPagePath(query);
      try {
        const init = await initFlightSearch(payload);
        if (init.ok && init.resultsPath) {
          resultsPath = init.resultsPath;
        }
      } catch {
        /* navigate without search_id — results page will init as before */
      }

      setSubmitState({ status: "redirecting", targetUrl: resultsPath });
      if (typeof document !== "undefined") {
        document.body.setAttribute("data-handoff-url", resultsPath);
      }
      // Hard-nav results handoff: soft router.push can stall 20–40s before the
      // results shell mounts (cert outlier: loading_shell≈33s, poll_count=1).
      // Supplier search already started above; hard assign preserves search_id overlap.
      if (typeof window !== "undefined") {
        window.location.assign(resultsPath);
      } else {
        router.push(resultsPath);
      }
      onSubmitted?.();
    },
    [onSubmitted, options, passengers, router],
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
      airline: groupAirline,
      sector: groupSector,
      category: groupCategory,
      travelDate: groupTravelDate,
    };
    const result = validateGroupSearch(draftInput, {
      airlineValues: groupAirlineValues,
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

  const handleGroupClear = () => {
    setGroupAirline("");
    setGroupSector("");
    setGroupCategory("all");
    setGroupTravelDate("");
    setErrors([]);
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

  const compact = layout === "compact";

  return (
    <section
      className={cn(
        "overflow-visible rounded-jp-card border border-white/60 bg-[rgba(248,250,252,0.95)] shadow-[0_10px_40px_rgba(15,23,42,0.08),inset_0_1px_0_rgba(255,255,255,0.65)] backdrop-blur-md dark:border-white/10 dark:bg-[rgba(30,41,59,0.78)]",
        compact ? "p-jp-md sm:p-jp-lg" : "p-jp-lg sm:p-jp-xl",
        className,
      )}
      aria-label="Flight search"
      data-testid="search-module"
      data-search-layout={layout}
      data-search-variant={variant}
    >
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        {variant === "home" ? (
          <ProductSearchTabs productTab={productTab} onProductTabChange={handleProductTabChange} compact={compact} />
        ) : (
          <p className="text-sm font-semibold text-jp-text">Edit search</p>
        )}
        {productTab === "flights" ? (
          <TripTypeDropdown tripType={tripType} onTripTypeChange={handleTripTypeChange} compact={compact} />
        ) : null}
      </div>

      <div
        className={cn(
          "mt-jp-md transition-opacity duration-ui",
          compact ? "min-h-0" : "min-h-[18rem]",
        )}
      >
        {productTab === "flights" && tripType === "one_way" ? (
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
            layout={layout}
          />
        ) : null}

        {productTab === "flights" && tripType === "return" ? (
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
            layout={layout}
          />
        ) : null}

        {productTab === "flights" && tripType === "multi_city" ? (
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

        {productTab === "group" ? (
          <SharedGroupSearch
            values={{
              airline: groupAirline,
              sector: groupSector,
              category: groupCategory,
              travelDate: groupTravelDate,
            }}
            facetsState={groupFacets.state}
            airlines={groupFacets.airlines}
            sectors={groupFacets.sectors}
            categories={groupFacets.categories}
            dateBounds={groupFacets.dateBounds}
            facetsError={groupFacets.errorMessage}
            onRetryFacets={groupFacets.retry}
            onChange={(next) => {
              setGroupAirline(next.airline);
              setGroupSector(next.sector);
              setGroupCategory(next.category);
              setGroupTravelDate(next.travelDate);
            }}
            onSubmit={handleGroupSubmit}
            onClear={handleGroupClear}
            errors={errors}
            disabled={isSubmitting}
          />
        ) : null}
      </div>

      <SearchStatusBanner state={submitState} />
    </section>
  );
}
