"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { initFlightSearch } from "@/services/flight-search";
import { fetchFlightResultsData } from "../services/flight-results-api";
import type { ActiveResultsFilters, FlightResultsDataResponse, ResultsPageStatus } from "../types";
import { resolveLaravelSort, type UiSortKey } from "../utils/sorting";
import { criteriaFromSearchParams } from "../utils/criteria-from-params";
import { searchIdentityKey } from "../utils/search-identity";
import {
  isActiveSearchStatus,
  isTerminalSearchStatus,
  mergeProgressiveResults,
  resolvePipelineStatus,
} from "../utils/merge-results";

export type UseFlightResultsOptions = {
  searchId: string | null;
  searchParams: URLSearchParams;
  sort: UiSortKey;
  filters: ActiveResultsFilters;
  view?: string | null;
};

const POLL_INTERVAL_MS = 750;
const TERMINAL_STATUSES = new Set(["ready", "empty", "failed", "expired", "error"]);

function stagedSearchMessage(elapsedMs: number, tripType: string, hasResults: boolean): string {
  // Pending suppliers must never read as a failure warning.
  if (hasResults) {
    return "Updating fares…";
  }
  if (tripType === "round_trip") {
    if (elapsedMs < 2000) return "Finding outbound and return options…";
    if (elapsedMs < 6000) return "Checking live airline fares…";
    if (elapsedMs < 12000) return "Still searching — live airline responses can take a few moments.";
    return "Updating fares… some airlines are still responding.";
  }
  if (elapsedMs < 2000) return "Searching live flights…";
  if (elapsedMs < 6000) return "Checking available fares…";
  if (elapsedMs < 12000) return "Still searching — live airline responses can take a few moments.";
  return "Updating fares… some airlines are still responding.";
}

function countVisibleResults(payload: FlightResultsDataResponse | null): number {
  if (!payload) return 0;
  return (
    (payload.offers ?? []).length +
    (payload.outbound_options ?? []).length +
    (payload.paired_options ?? []).length
  );
}

function mapPipelineToPageStatus(
  pipeline: string,
  payload: FlightResultsDataResponse,
): ResultsPageStatus {
  const visible = countVisibleResults(payload);
  if (pipeline === "failed") {
    // Owner blocker: valid inventory must not be suppressed by a fatal banner.
    // Secondary/supplier-partial failures with usable rows render as ready + soft warning.
    if (visible > 0) return "ready";
    return "failed";
  }
  if (pipeline === "searching" || pipeline === "queued" || pipeline === "in_progress") {
    return visible > 0 ? "partial" : "searching";
  }
  if (pipeline === "partial") return "partial";
  if (pipeline === "empty") return "empty";
  if (pipeline === "ready") {
    return visible === 0 && (payload.total ?? 0) === 0 ? "empty" : "ready";
  }
  if (visible > 0 || (payload.total ?? 0) > 0) return "ready";
  return "empty";
}

export function useFlightResults({ searchId, searchParams, sort, filters, view }: UseFlightResultsOptions) {
  const [resolvedSearchId, setResolvedSearchId] = useState<string | null>(searchId);
  const [status, setStatus] = useState<ResultsPageStatus>("idle");
  const [message, setMessage] = useState("Searching flights…");
  const [data, setData] = useState<FlightResultsDataResponse | null>(null);
  const [page, setPage] = useState(1);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [searchStillActive, setSearchStillActive] = useState(false);
  const abortRef = useRef<AbortController | null>(null);
  const pollAbortRef = useRef<AbortController | null>(null);
  const requestSeq = useRef(0);
  const readyRef = useRef(false);
  const lastBootstrappedId = useRef<string | null>(null);
  const skipNextFilterRefresh = useRef(true);
  const lastViewKeyRef = useRef<string | null>(null);
  const searchStartedAt = useRef<number>(Date.now());
  const pollTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const filtersKey = JSON.stringify(filters);
  const laravelSort = resolveLaravelSort(sort);
  const identity = searchIdentityKey(searchParams);
  const viewKey = view ?? "";
  const tripType = searchParams.get("trip_type") ?? "one_way";

  const stopPolling = useCallback(() => {
    if (pollTimerRef.current) {
      clearTimeout(pollTimerRef.current);
      pollTimerRef.current = null;
    }
    pollAbortRef.current?.abort();
    pollAbortRef.current = null;
    setSearchStillActive(false);
  }, []);

  const applyPayload = useCallback(
    (payload: FlightResultsDataResponse, mode: "replace" | "merge") => {
      const pipeline = resolvePipelineStatus(payload);
      let merged: FlightResultsDataResponse = payload;
      setData((current) => {
        merged = mode === "merge" ? mergeProgressiveResults(current, payload) : payload;
        return merged;
      });
      const visible = countVisibleResults(merged);
      const nextStatus = mapPipelineToPageStatus(pipeline, merged);
      const elapsed = Date.now() - searchStartedAt.current;

      if (isActiveSearchStatus(pipeline)) {
        setStatus(visible > 0 ? "partial" : "searching");
        setMessage(stagedSearchMessage(elapsed, tripType, visible > 0));
        setSearchStillActive(true);
        readyRef.current = visible > 0;
        return { shouldPoll: true, nextStatus: visible > 0 ? "partial" : "searching" };
      }

      setSearchStillActive(false);
      if (nextStatus === "empty") {
        setStatus("empty");
        setMessage(payload.empty_message ?? "No flights match your search.");
      } else if (nextStatus === "failed") {
        setStatus("failed");
        setMessage(payload.message ?? "We could not complete your flight search. Please try again.");
      } else {
        setStatus(nextStatus === "partial" ? "partial" : "ready");
        // Settled incomplete suppliers — compact notice, not an alarming yellow failure.
        if (pipeline === "failed" && countVisibleResults(merged) > 0) {
          setMessage(
            payload.message?.trim()
              ? `${payload.message} Showing available flights.`
              : "Some additional airline fares are temporarily unavailable.",
          );
        } else {
          setMessage("");
        }
      }
      readyRef.current = true;
      return { shouldPoll: false, nextStatus };
    },
    [tripType],
  );

  const loadPage = useCallback(
    async (id: string, targetPage: number, append: boolean, phase: "init" | "refresh" | "poll") => {
      if (phase !== "poll") {
        abortRef.current?.abort();
      }
      const controller = new AbortController();
      if (phase === "poll") {
        pollAbortRef.current?.abort();
        pollAbortRef.current = controller;
      } else {
        abortRef.current = controller;
      }
      const seq = ++requestSeq.current;

      if (!append && phase !== "poll") {
        setStatus(phase === "init" ? "initializing" : "loading");
        setMessage(phase === "init" ? "Searching flights…" : "Finding the best available flights…");
      } else if (append) {
        setIsLoadingMore(true);
      }

      const response = await fetchFlightResultsData({
        searchId: id,
        page: targetPage,
        perPage: 12,
        sort: laravelSort,
        filters,
        view: viewKey || undefined,
        signal: controller.signal,
      });

      if (seq !== requestSeq.current || controller.signal.aborted) {
        return { shouldPoll: false };
      }

      if (!response.ok) {
        if (response.status === 0 && response.message === "Request cancelled.") {
          return { shouldPoll: false };
        }
        if (phase === "poll") {
          return { shouldPoll: true };
        }
        // Primary search/refresh failure: do not keep prior inventory visible as current.
        setData(null);
        setStatus(response.status === 410 ? "expired" : "error");
        setMessage(response.message);
        setIsLoadingMore(false);
        setSearchStillActive(false);
        return { shouldPoll: false };
      }

      const payload = response.data;
      const pipeline = resolvePipelineStatus(payload);
      // Progressive polls merge while search is ACTIVE; terminal ready/empty/failed
      // must reconcile to canonical backend truth (never permanently retain rejected partials).
      const shouldMerge =
        append || (phase === "poll" && isActiveSearchStatus(pipeline) && !isTerminalSearchStatus(pipeline));
      const result = applyPayload(payload, shouldMerge ? "merge" : "replace");
      setPage(targetPage);
      setIsLoadingMore(false);
      return result;
    },
    [applyPayload, filters, laravelSort, viewKey],
  );

  const schedulePoll = useCallback(
    (id: string) => {
      stopPolling();
      setSearchStillActive(true);
      const tick = async () => {
        const result = await loadPage(id, 1, false, "poll");
        if (!result?.shouldPoll) {
          stopPolling();
          return;
        }
        pollTimerRef.current = setTimeout(() => {
          void tick();
        }, POLL_INTERVAL_MS);
      };
      pollTimerRef.current = setTimeout(() => {
        void tick();
      }, POLL_INTERVAL_MS);
    },
    [loadPage, stopPolling],
  );

  useEffect(() => {
    let cancelled = false;

    const bootstrap = async () => {
      if (searchId && lastBootstrappedId.current === searchId) {
        return;
      }

      stopPolling();
      searchStartedAt.current = Date.now();
      setStatus("initializing");
      setMessage("Searching flights…");
      setData(null);
      readyRef.current = false;
      skipNextFilterRefresh.current = true;
      lastViewKeyRef.current = viewKey;

      let id = searchId;
      if (!id) {
        const criteria = criteriaFromSearchParams(searchParams);
        if (!criteria) {
          if (!cancelled) {
            setStatus("error");
            setMessage("Missing search details. Please start a new search.");
          }
          return;
        }
        const init = await initFlightSearch(criteria);
        if (cancelled) return;
        if (!init.ok) {
          setStatus("failed");
          setMessage(init.message);
          return;
        }
        id = init.data.search_id;
        setResolvedSearchId(id);
        const nextParams = new URLSearchParams(searchParams);
        nextParams.set("search_id", id);
        window.history.replaceState(null, "", `/flights/results?${nextParams.toString()}`);
      } else {
        setResolvedSearchId(id);
      }

      const result = await loadPage(id, 1, false, "init");
      lastBootstrappedId.current = id;
      if (!cancelled && result?.shouldPoll) {
        schedulePoll(id);
      }
    };

    void bootstrap();

    return () => {
      cancelled = true;
      abortRef.current?.abort();
      stopPolling();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps -- bootstrap only when search identity changes
  }, [identity]);

  useEffect(() => {
    if (!resolvedSearchId) return;

    const previousView = lastViewKeyRef.current;
    const viewChanged = previousView !== null && previousView !== viewKey;
    lastViewKeyRef.current = viewKey;

    // Never skip a view change — missing this refetch caused Pair to stay on Segmented
    // cards until a later navigation (e.g. browser Back).
    if (skipNextFilterRefresh.current && !viewChanged) {
      skipNextFilterRefresh.current = false;
      return;
    }
    skipNextFilterRefresh.current = false;

    if (!readyRef.current && status !== "partial" && status !== "ready" && !viewChanged) return;
    // View / filter / sort changes must not leave the prior flow's cards on screen.
    setData(null);
    setStatus("loading");
    setMessage("Finding the best available flights…");
    void loadPage(resolvedSearchId, 1, false, "refresh");
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filtersKey, laravelSort, resolvedSearchId, viewKey]);

  useEffect(() => () => stopPolling(), [stopPolling]);

  const retry = useCallback(async () => {
    stopPolling();
    readyRef.current = false;
    searchStartedAt.current = Date.now();
    const criteria = criteriaFromSearchParams(searchParams);
    if (!criteria) {
      setStatus("error");
      setMessage("Missing search details. Please start a new search.");
      return;
    }
    const init = await initFlightSearch(criteria);
    if (!init.ok) {
      setStatus("failed");
      setMessage(init.message);
      return;
    }
    const id = init.data.search_id;
    setResolvedSearchId(id);
    lastBootstrappedId.current = id;
    const result = await loadPage(id, 1, false, "init");
    if (result?.shouldPoll) {
      schedulePoll(id);
    }
  }, [loadPage, schedulePoll, searchParams, stopPolling]);

  const loadMore = useCallback(() => {
    if (!resolvedSearchId || !data?.has_more || isLoadingMore) return;
    void loadPage(resolvedSearchId, page + 1, true, "refresh");
  }, [data?.has_more, isLoadingMore, loadPage, page, resolvedSearchId]);

  const visibleCount = useMemo(() => countVisibleResults(data), [data]);

  return {
    status,
    message,
    data,
    resolvedSearchId,
    offers: useMemo(() => data?.offers ?? [], [data]),
    outboundOptions: useMemo(() => data?.outbound_options ?? [], [data]),
    pairedOptions: useMemo(() => data?.paired_options ?? [], [data]),
    isReturnSplit: data?.flow === "return_split_outbound",
    isReturnPair: data?.flow === "return_pair",
    pairingAuthority: data?.pairing_authority ?? null,
    freshness: data?.search_freshness ?? null,
    page,
    hasMore: Boolean(data?.has_more),
    total: data?.total ?? visibleCount,
    isLoadingMore,
    searchStillActive,
    visibleCount,
    retry,
    loadMore,
  };
}

export function buildSearchSummaryFromParams(params: URLSearchParams) {
  const tripType = params.get("trip_type") ?? "one_way";
  const adults = Number(params.get("adults") ?? "1");
  const children = Number(params.get("children") ?? "0");
  const infants = Number(params.get("infants") ?? "0");
  const travelerParts = [`${adults} adult${adults === 1 ? "" : "s"}`];
  if (children > 0) travelerParts.push(`${children} child${children === 1 ? "" : "ren"}`);
  if (infants > 0) travelerParts.push(`${infants} infant${infants === 1 ? "" : "s"}`);

  return {
    origin: params.get("from") ?? "—",
    destination: params.get("to") ?? "—",
    tripType: tripType === "round_trip" ? "Return" : tripType === "multi_city" ? "Multi-city" : "One way",
    departureDate: params.get("depart") ?? "—",
    returnDate: params.get("return_date") ?? undefined,
    passengersLabel: travelerParts.join(", "),
    cabin: (params.get("cabin") ?? "economy").replace(/_/g, " "),
    directOnly: params.get("stops") === "direct",
    nearbyAirports: params.get("include_nearby") === "1",
    flexibleDates: params.get("flexible_dates") === "1",
  };
}

export { TERMINAL_STATUSES };
