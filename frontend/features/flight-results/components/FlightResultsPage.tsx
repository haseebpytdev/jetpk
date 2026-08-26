"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { FlightDetailsDrawer, type FlightDetailsContext } from "@/features/flight-details";
import { SearchModule } from "@/features/search";
import { buildSearchSummaryFromParams, useFlightResults } from "../hooks/use-flight-results";
import { parseFiltersFromSearchParams } from "../utils/filters";
import { parseUiSort, type UiSortKey } from "../utils/sorting";
import {
  buildFreshResultsSearchParams,
  clearResultsLeftForCheckout,
  shouldRefreshResultsAfterCheckoutReturn,
} from "../utils/checkout-nav";
import { EmptyResultsState } from "./EmptyResultsState";
import { ExpiredSearchState } from "./ExpiredSearchState";
import { NearbyDateStrip } from "./NearbyDateStrip";
import { FlightResultCard } from "./FlightResultCard";
import { LoadMoreControl } from "./LoadMoreControl";
import { MobileFilterDrawer } from "./MobileFilterDrawer";
import { OutboundOptionCard } from "./OutboundOptionCard";
import { PairReturnCard } from "./PairReturnCard";
import { PartialResultsNotice } from "./PartialResultsNotice";
import { ResultSkeleton } from "./ResultSkeleton";
import { ResultsFilterPanel } from "./ResultsFilterPanel";
import { ResultsToolbar } from "./ResultsToolbar";
import { ReturnViewSelector } from "./ReturnViewSelector";
import { SearchErrorState } from "./SearchErrorState";
import { SearchProgress } from "./SearchProgress";
import { ResultsHeroBand } from "./ResultsHeroBand";
import { SearchSummaryBar } from "./SearchSummaryBar";

export function FlightResultsPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const params = useMemo(() => new URLSearchParams(searchParams.toString()), [searchParams]);
  const searchId = params.get("search_id");
  const tripType = params.get("trip_type");
  const viewParam = params.get("view");
  const isReturn = tripType === "round_trip";
  const needsViewChoice = isReturn && viewParam !== "pair" && viewParam !== "segmented";
  const [sort, setSort] = useState<UiSortKey>(() => parseUiSort(params.get("sort")));
  const [filters, setFilters] = useState(() => parseFiltersFromSearchParams(params));
  const [editOpen, setEditOpen] = useState(false);
  const [filtersOpen, setFiltersOpen] = useState(false);
  const [detailsContext, setDetailsContext] = useState<FlightDetailsContext | null>(null);
  const [resultsStaleLocked, setResultsStaleLocked] = useState(false);
  const detailsTriggerRef = useRef<HTMLElement | null>(null);
  const filterButtonRef = useRef<HTMLButtonElement>(null);
  const freshSearchInFlight = useRef(false);

  const summary = useMemo(() => buildSearchSummaryFromParams(params), [params]);

  const results = useFlightResults({
    searchId,
    searchParams: params,
    sort,
    filters,
    view: viewParam,
  });

  const startFreshSearchFromCheckoutReturn = useCallback(() => {
    if (freshSearchInFlight.current) return;
    freshSearchInFlight.current = true;
    setResultsStaleLocked(true);
    setDetailsContext(null);
    clearResultsLeftForCheckout();
    const next = buildFreshResultsSearchParams(params);
    router.replace(`/flights/results?${next.toString()}`, { scroll: false });
    window.setTimeout(() => {
      freshSearchInFlight.current = false;
    }, 1500);
  }, [params, router]);

  useEffect(() => {
    const onPageShow = (event: PageTransitionEvent) => {
      if (!shouldRefreshResultsAfterCheckoutReturn(event)) return;
      startFreshSearchFromCheckoutReturn();
    };
    window.addEventListener("pageshow", onPageShow);
    return () => window.removeEventListener("pageshow", onPageShow);
  }, [startFreshSearchFromCheckoutReturn]);

  useEffect(() => {
    if (results.resolvedSearchId && results.status !== "idle" && results.status !== "initializing") {
      setResultsStaleLocked(false);
    }
  }, [results.resolvedSearchId, results.status]);

  const syncUrl = useCallback(
    (nextFilters: typeof filters, nextSort: UiSortKey, extra?: Record<string, string | null>) => {
      const next = new URLSearchParams(params);
      next.set("sort", nextSort);
      [
        "airline",
        "stops",
        "refundable",
        "cabin_filter",
        "baggage",
        "departure_window",
        "arrival_window",
        "min_price",
        "max_price",
        "max_duration",
        "duration_bucket",
        "layover_airport",
        "fare_family",
        "bookable_only",
        "operating_airline",
        "flight_number",
      ].forEach((key) => next.delete(key));
      // Never delete search criteria `cabin` — only sync facet `cabin_filter`.
      Object.entries(nextFilters).forEach(([key, value]) => {
        if (!value) return;
        if (key === "cabin") {
          next.set("cabin_filter", value);
          return;
        }
        next.set(key, value);
      });
      if (extra) {
        Object.entries(extra).forEach(([key, value]) => {
          if (value === null) next.delete(key);
          else next.set(key, value);
        });
      }
      router.replace(`/flights/results?${next.toString()}`, { scroll: false });
    },
    [params, router],
  );

  const handleFiltersChange = (next: typeof filters) => {
    setFilters(next);
    syncUrl(next, sort);
  };

  const handleSortChange = (next: UiSortKey) => {
    setSort(next);
    syncUrl(filters, next);
  };

  const handleClearFilters = () => {
    setFilters({});
    syncUrl({}, sort);
  };

  const setReturnView = useCallback(
    (view: "pair" | "segmented") => {
      syncUrl(filters, sort, { view, outbound_key: null, combo_id: null, fare_option_key: null });
    },
    [filters, sort, syncUrl],
  );

  const openDetails = useCallback(
    (offer: import("../types").FlightOffer, fareOptionKey: string, intent: "details" | "booking") => {
      if (resultsStaleLocked) return;
      const resolvedSearchId = results.resolvedSearchId ?? searchId ?? "";
      if (!resolvedSearchId) return;
      setDetailsContext({
        searchId: resolvedSearchId,
        offerId: offer.offer_id,
        fareOptionKey,
        initialOffer: offer,
        initialFareOptions: offer.branded_fares_display_options ?? offer.fare_family_options_display,
        intent,
        legMode: "one_way",
      });
    },
    [results.resolvedSearchId, resultsStaleLocked, searchId],
  );

  const openOutboundFareConfirmation = useCallback(
    (
      option: import("../types").OutboundOption,
      offer: import("../types").FlightOffer,
      fareOptionKey: string,
      intent: "details" | "booking",
    ) => {
      if (resultsStaleLocked) return;
      const resolvedSearchId = results.resolvedSearchId ?? searchId ?? "";
      if (!resolvedSearchId) return;
      setDetailsContext({
        searchId: resolvedSearchId,
        offerId: option.outbound_key,
        outboundKey: option.outbound_key,
        fareOptionKey,
        initialOffer: offer,
        initialFareOptions: offer.branded_fares_display_options ?? offer.fare_family_options_display,
        intent,
        legMode: "outbound_confirm",
      });
    },
    [results.resolvedSearchId, resultsStaleLocked, searchId],
  );

  const openPairFareConfirmation = useCallback(
    (pair: import("../types").PairedReturnOption, fareOptionKey?: string) => {
      if (resultsStaleLocked) return;
      const resolvedSearchId = results.resolvedSearchId ?? searchId ?? "";
      if (!resolvedSearchId) return;
      setDetailsContext({
        searchId: resolvedSearchId,
        offerId: pair.combo_id,
        comboId: pair.combo_id,
        outboundKey: pair.outbound_key,
        fareOptionKey,
        intent: "booking",
        legMode: "pair",
      });
    },
    [results.resolvedSearchId, resultsStaleLocked, searchId],
  );

  const closeDetails = useCallback(() => {
    setDetailsContext(null);
    detailsTriggerRef.current?.focus();
  }, []);

  const shownCount = results.isReturnPair
    ? results.pairedOptions.length
    : results.isReturnSplit
      ? results.outboundOptions.length
      : results.offers.length;
  const isBootstrapping =
    results.status === "idle" || results.status === "loading" || results.status === "initializing";
  const isSearchingMask = results.status === "searching" || (isBootstrapping && shownCount === 0);
  const showResultsList =
    !resultsStaleLocked &&
    (results.status === "ready" || results.status === "partial" || (shownCount > 0 && results.status !== "empty"));
  const isLoading = isBootstrapping && shownCount === 0;

  return (
    <div className="w-full">
      <h1 className="sr-only">Flight search results</h1>

      <div className="relative">
        <ResultsHeroBand />
        <div className="relative z-10 mx-auto max-w-7xl px-4 pb-1 pt-3 sm:px-6 lg:px-8">
          <SearchSummaryBar summary={summary} onModifyClick={() => setEditOpen((open) => !open)} />
        </div>
      </div>

      <div className="mx-auto w-full max-w-7xl space-y-2.5 px-4 pb-6 pt-2.5 sm:px-6 lg:px-8">
        {editOpen ? (
          <div data-testid="inline-edit-search">
            <SearchModule
              variant="results"
              layout="compact"
              initialParams={params}
              onSubmitted={() => setEditOpen(false)}
            />
          </div>
        ) : null}

        {isReturn ? (
          <div className="flex flex-wrap items-center gap-2 text-sm" data-testid="return-view-switch">
            <span className="text-jp-text-muted">View:</span>
            <button
              type="button"
              className={`rounded-jp-md px-2 py-1 ${viewParam === "pair" ? "bg-jp-primary text-white" : "border border-jp-border"}`}
              onClick={() => setReturnView("pair")}
            >
              Pair
            </button>
            <button
              type="button"
              className={`rounded-jp-md px-2 py-1 ${viewParam === "segmented" ? "bg-jp-primary text-white" : "border border-jp-border"}`}
              onClick={() => setReturnView("segmented")}
            >
              Segmented
            </button>
          </div>
        ) : null}

        {results.isReturnSplit ? (
          <ol className="flex gap-3 text-xs font-medium text-jp-text-muted" data-testid="segmented-progress">
            <li className="text-jp-primary">1. Outbound</li>
            <li>2. Return</li>
            <li>3. Fare &amp; Travelers</li>
          </ol>
        ) : null}

        {results.freshness?.expires_display ? (
          <p className="text-xs text-jp-text-muted" data-testid="search-expiry">
            {results.freshness.expires_display}
          </p>
        ) : null}

        <PartialResultsNotice warnings={results.data?.warnings} />

        {results.status === "ready" && !results.isReturnSplit && !results.isReturnPair ? (
          <NearbyDateStrip searchId={results.resolvedSearchId ?? ""} hidden={results.isReturnSplit} />
        ) : null}

        <ResultsToolbar
          sort={sort}
          onSortChange={handleSortChange}
          filters={filters}
          onOpenFilters={() => setFiltersOpen(true)}
          filterButtonRef={filterButtonRef}
          total={results.total}
          status={results.status}
          loadingMessage={results.message}
          searchStillActive={results.searchStillActive}
        />

        <div className="grid gap-4 lg:grid-cols-[minmax(0,14.5rem)_minmax(0,1fr)]">
          <div className="hidden min-w-0 max-w-full lg:block">
            <ResultsFilterPanel
              facets={results.data?.filters}
              filters={filters}
              onChange={handleFiltersChange}
              onClearAll={handleClearFilters}
              loading={isLoading || isSearchingMask}
            />
          </div>

          <div className="min-w-0 space-y-3">
            {isSearchingMask || resultsStaleLocked ? (
              <>
                <SearchProgress
                  message={
                    resultsStaleLocked
                      ? "Refreshing live fares for your search…"
                      : results.message || "Searching flights…"
                  }
                  summary={summary}
                />
                <ResultSkeleton />
              </>
            ) : null}

            {results.searchStillActive && shownCount > 0 ? (
              <SearchProgress
                compact
                message={
                  results.total > 0
                    ? `${results.total} flights found · checking for more`
                    : results.message || "Searching for more flights…"
                }
              />
            ) : null}

            {results.status === "error" || results.status === "failed" ? (
              <SearchErrorState message={results.message} onRetry={results.retry} />
            ) : null}

            {results.status === "expired" ? (
              <ExpiredSearchState message={results.message} onNewSearch={() => setEditOpen(true)} />
            ) : null}

            {results.status === "empty" && results.isReturnPair ? (
              <div className="rounded-jp-card border border-jp-border bg-jp-surface p-6" data-testid="pair-empty">
                <p className="font-medium text-jp-text">No paired return options are currently available.</p>
                <button
                  type="button"
                  className="mt-3 rounded-jp-md bg-jp-primary px-3 py-2 text-sm font-semibold text-white"
                  onClick={() => setReturnView("segmented")}
                >
                  Switch to Segmented View
                </button>
              </div>
            ) : null}

            {results.status === "empty" && !results.isReturnPair ? (
              <EmptyResultsState message={results.message} onNewSearch={() => setEditOpen(true)} />
            ) : null}

            {showResultsList ? (
              <div role="list" className="space-y-3" aria-label="Flight results">
                {results.isReturnPair
                  ? results.pairedOptions.map((option) => (
                      <div key={option.combo_id} role="listitem">
                        <PairReturnCard
                          option={option}
                          onDetails={openPairFareConfirmation}
                          onSelect={openPairFareConfirmation}
                        />
                      </div>
                    ))
                  : results.isReturnSplit
                    ? results.outboundOptions.map((option) => (
                        <div key={option.outbound_key} role="listitem">
                          <OutboundOptionCard
                            option={option}
                            searchId={results.resolvedSearchId ?? ""}
                            onOpenDetails={openOutboundFareConfirmation}
                          />
                        </div>
                      ))
                    : results.offers.map((offer) => (
                        <div key={offer.offer_id} role="listitem">
                          <FlightResultCard
                            offer={offer}
                            searchId={results.resolvedSearchId ?? ""}
                            searchParams={params}
                            onOpenDetails={openDetails}
                          />
                        </div>
                      ))}
              </div>
            ) : null}

            {results.status === "ready" ? (
              <LoadMoreControl
                hasMore={results.hasMore}
                loading={results.isLoadingMore}
                onLoadMore={results.loadMore}
                total={results.total}
                shown={shownCount}
              />
            ) : null}
          </div>
        </div>

        <MobileFilterDrawer
          open={filtersOpen}
          onClose={() => setFiltersOpen(false)}
          facets={results.data?.filters}
          filters={filters}
          onChange={handleFiltersChange}
          onClearAll={handleClearFilters}
          triggerRef={filterButtonRef}
        />

        <ReturnViewSelector open={needsViewChoice} onSelect={setReturnView} />

        <FlightDetailsDrawer
          open={detailsContext !== null}
          context={detailsContext}
          onClose={closeDetails}
          triggerRef={detailsTriggerRef}
          onNewSearch={() => setEditOpen(true)}
        />
      </div>
    </div>
  );
}
