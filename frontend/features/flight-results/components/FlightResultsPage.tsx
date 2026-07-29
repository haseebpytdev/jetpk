"use client";

import { useCallback, useMemo, useRef, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { buildSearchSummaryFromParams, useFlightResults } from "../hooks/use-flight-results";
import { useOfferSelection } from "../hooks/use-offer-selection";
import { parseFiltersFromSearchParams } from "../utils/filters";
import { parseUiSort, type UiSortKey } from "../utils/sorting";
import { EmptyResultsState } from "./EmptyResultsState";
import { ExpiredSearchState } from "./ExpiredSearchState";
import { FlightResultCard } from "./FlightResultCard";
import { LoadMoreControl } from "./LoadMoreControl";
import { MobileFilterDrawer } from "./MobileFilterDrawer";
import { ModifySearchPanel } from "./ModifySearchPanel";
import { OutboundOptionCard } from "./OutboundOptionCard";
import { PartialResultsNotice } from "./PartialResultsNotice";
import { ResultSkeleton } from "./ResultSkeleton";
import { ResultsFilterPanel } from "./ResultsFilterPanel";
import { ResultsToolbar } from "./ResultsToolbar";
import { SearchErrorState } from "./SearchErrorState";
import { SearchProgress } from "./SearchProgress";
import { SearchSummaryBar } from "./SearchSummaryBar";

export function FlightResultsPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const params = useMemo(() => new URLSearchParams(searchParams.toString()), [searchParams]);
  const searchId = params.get("search_id");
  const [sort, setSort] = useState<UiSortKey>(() => parseUiSort(params.get("sort")));
  const [filters, setFilters] = useState(() => parseFiltersFromSearchParams(params));
  const [modifyOpen, setModifyOpen] = useState(false);
  const [filtersOpen, setFiltersOpen] = useState(false);
  const filterButtonRef = useRef<HTMLButtonElement>(null);

  const summary = useMemo(() => buildSearchSummaryFromParams(params), [params]);

  const results = useFlightResults({
    searchId,
    searchParams: params,
    sort,
    filters,
  });

  const selection = useOfferSelection(results.resolvedSearchId ?? searchId ?? "");

  const syncUrl = useCallback(
    (nextFilters: typeof filters, nextSort: UiSortKey) => {
      const next = new URLSearchParams(params);
      next.set("sort", nextSort);
      [
        "airline",
        "stops",
        "refundable",
        "cabin",
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
      ].forEach((key) => next.delete(key));
      Object.entries(nextFilters).forEach(([key, value]) => {
        if (value) next.set(key, value);
      });
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

  const shownCount = results.isReturnSplit ? results.outboundOptions.length : results.offers.length;
  const isLoading = results.status === "loading" || results.status === "initializing";

  return (
    <div className="mx-auto w-full max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
      <h1 className="sr-only">Flight search results</h1>

      <SearchSummaryBar summary={summary} onModifyClick={() => setModifyOpen(true)} />

      {results.freshness?.expires_display ? (
        <p className="text-xs text-jp-text-muted" data-testid="search-expiry">
          {results.freshness.expires_display}
        </p>
      ) : null}

      <PartialResultsNotice warnings={results.data?.warnings} />

      <ResultsToolbar
        sort={sort}
        onSortChange={handleSortChange}
        filters={filters}
        onOpenFilters={() => setFiltersOpen(true)}
        filterButtonRef={filterButtonRef}
        total={results.total}
      />

      <div className="grid gap-6 lg:grid-cols-[16rem_1fr]">
        <div className="hidden lg:block">
          <ResultsFilterPanel
            facets={results.data?.filters}
            filters={filters}
            onChange={handleFiltersChange}
            onClearAll={handleClearFilters}
          />
        </div>

        <div className="min-w-0 space-y-4">
          {isLoading ? (
            <>
              <SearchProgress message={results.message || "Loading results…"} />
              <ResultSkeleton />
            </>
          ) : null}

          {results.status === "error" || results.status === "failed" ? (
            <SearchErrorState message={results.message} onRetry={results.retry} />
          ) : null}

          {results.status === "expired" ? (
            <ExpiredSearchState message={results.message} onNewSearch={() => setModifyOpen(true)} />
          ) : null}

          {results.status === "empty" ? (
            <EmptyResultsState message={results.message} onNewSearch={() => setModifyOpen(true)} />
          ) : null}

          {results.status === "ready" ? (
            <div role="list" className="space-y-4" aria-label="Flight results">
              {results.isReturnSplit
                ? results.outboundOptions.map((option) => (
                    <div key={option.outbound_key} role="listitem">
                      <OutboundOptionCard option={option} searchId={results.resolvedSearchId ?? ""} />
                    </div>
                  ))
                : results.offers.map((offer) => (
                    <div key={offer.offer_id} role="listitem">
                      <FlightResultCard
                        offer={offer}
                        searchId={results.resolvedSearchId ?? ""}
                        selecting={selection.selectingId === offer.offer_id}
                        onSelect={selection.selectOffer}
                      />
                    </div>
                  ))}
            </div>
          ) : null}

          {selection.error ? (
            <p className="text-sm text-red-700" role="alert">
              {selection.error}
            </p>
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

      <ModifySearchPanel open={modifyOpen} onClose={() => setModifyOpen(false)} />
    </div>
  );
}
