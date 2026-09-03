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

/** REG-04: after store delivery fix, cadence bounds pair→browser worst case (~interval + RTT). */
const POLL_INTERVAL_MS = 200;
/** Bound infinite skeleton: no usable rows after this → truthful timeout UI. */
const CLIENT_SEARCH_DEADLINE_MS = 60_000;
/** With partial rows, stop waiting on straggler suppliers after this. */
const CLIENT_SEARCH_SETTLE_MS = 90_000;
const TERMINAL_STATUSES = new Set(["ready", "empty", "failed", "expired", "error"]);

function stagedSearchMessage(elapsedMs: number, tripType: string, hasResults: boolean): string {
  // Pending suppliers must never read as a failure warning.
  if (hasResults) {
    return "Checking for more options…";
  }
  if (tripType === "round_trip") {
    if (elapsedMs < 2500) return "Searching live return fares";
    if (elapsedMs < 7000) return "Matching outbound and return options";
    if (elapsedMs < 12000) return "Still searching — live airline responses can take a few moments.";
    return "Checking for more options… some airlines are still responding.";
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
  const pollGenerationRef = useRef(0);
  const readyRef = useRef(false);
  const lastBootstrappedId = useRef<string | null>(null);
  const skipNextFilterRefresh = useRef(true);
  const lastViewKeyRef = useRef<string | null>(null);
  /** JP-NEXT-PERF-02B: cache Pair/Segmented representations keyed to same search_id. */
  const viewPayloadCacheRef = useRef<Map<string, FlightResultsDataResponse>>(new Map());
  const dataRef = useRef<FlightResultsDataResponse | null>(null);
  const searchStartedAt = useRef<number>(Date.now());
  const pollTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const lastEmptyPollMessageAt = useRef(0);
  const filtersKey = JSON.stringify(filters);
  const laravelSort = resolveLaravelSort(sort);
  const identity = searchIdentityKey(searchParams);
  const viewKey = view ?? "";
  const tripType = searchParams.get("trip_type") ?? "one_way";

  const representationCacheKey = useCallback(
    (id: string, forView: string) => `${id}|${forView}|${laravelSort}|${filtersKey}`,
    [filtersKey, laravelSort],
  );

  const stopPolling = useCallback(() => {
    if (pollTimerRef.current) {
      clearTimeout(pollTimerRef.current);
      pollTimerRef.current = null;
    }
    pollGenerationRef.current += 1;
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
        dataRef.current = merged;
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
        return { shouldPoll: true, nextStatus: visible > 0 ? "partial" : "searching", visible };
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
      return { shouldPoll: false, nextStatus, visible };
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
      // Preserve READY/partial during soft filter/sort refresh so cards stay mounted.
      setStatus((current) => {
        if (phase === "refresh" && (current === "ready" || current === "partial")) {
          return current;
        }
        return phase === "init" ? "initializing" : "loading";
      });
      setMessage(phase === "init" ? "Searching flights…" : phase === "refresh" ? "Updating results…" : "Finding the best available flights…");
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

      // Aborted/stale polls must not halt the cadence loop. A newer poll or refresh
      // bumps requestSeq / aborts the fetch; the scheduler decides whether to continue.
      if (controller.signal.aborted) {
        return { shouldPoll: true, visible: 0, aborted: true as const };
      }
      if (seq !== requestSeq.current) {
        return { shouldPoll: phase === "poll", visible: 0, stale: true as const };
      }

      if (!response.ok) {
        if (response.status === 0 && response.message === "Request cancelled.") {
          return { shouldPoll: false, visible: 0 };
        }
        if (phase === "poll") {
          return { shouldPoll: true, visible: 0 };
        }
        // Primary search/refresh failure: do not keep prior inventory visible as current.
        // View-switch soft refresh keeps prior representation if the other view fails.
        if (phase === "refresh" && readyRef.current && countVisibleResults(dataRef.current) > 0) {
          setIsLoadingMore(false);
          setMessage(response.message || "Could not switch view. Showing previous results.");
          return { shouldPoll: false, visible: countVisibleResults(dataRef.current) };
        }
        setData(null);
        setStatus(response.status === 410 ? "expired" : "error");
        setMessage(response.message);
        setIsLoadingMore(false);
        setSearchStillActive(false);
        return { shouldPoll: false, visible: 0 };
      }

      const payload = response.data;
      const pipeline = resolvePipelineStatus(payload);
      // Empty progressive polls: skip merge/setData churn while still searching.
      // Cadence + message updates remain; first non-empty poll applies normally.
      if (
        phase === "poll" &&
        countVisibleResults(payload) === 0 &&
        countVisibleResults(dataRef.current) === 0 &&
        isActiveSearchStatus(pipeline) &&
        !isTerminalSearchStatus(pipeline)
      ) {
        const elapsedEmpty = Date.now() - searchStartedAt.current;
        // Throttle React updates on empty polls — message churn was stretching poll cadence.
        if (elapsedEmpty - lastEmptyPollMessageAt.current >= 1000) {
          lastEmptyPollMessageAt.current = elapsedEmpty;
          setStatus("searching");
          setMessage(stagedSearchMessage(elapsedEmpty, tripType, false));
        }
        setSearchStillActive(true);
        return { shouldPoll: true, nextStatus: "searching" as const, visible: 0 };
      }
      // Progressive polls merge while search is ACTIVE; terminal ready/empty/failed
      // must reconcile to canonical backend truth (never permanently retain rejected partials).
      const shouldMerge =
        append || (phase === "poll" && isActiveSearchStatus(pipeline) && !isTerminalSearchStatus(pipeline));
      const result = applyPayload(payload, shouldMerge ? "merge" : "replace");
      if (countVisibleResults(payload) > 0 || pipeline === "ready" || pipeline === "partial") {
        viewPayloadCacheRef.current.set(representationCacheKey(id, viewKey), payload);
      }
      setPage(targetPage);
      setIsLoadingMore(false);
      return result;
    },
    [applyPayload, filters, laravelSort, representationCacheKey, tripType, viewKey],
  );

  const schedulePoll = useCallback(
    (id: string) => {
      stopPolling();
      setSearchStillActive(true);
      const generation = pollGenerationRef.current;
      const tick = async () => {
        if (pollGenerationRef.current !== generation) return;
        const tickStarted = Date.now();
        const elapsed = tickStarted - searchStartedAt.current;
        const result = await loadPage(id, 1, false, "poll");
        if (pollGenerationRef.current !== generation) return;
        // Aborted/stale: another poll/refresh superseded this tick — keep cadence alive.
        const pollFlags = result as { aborted?: boolean; stale?: boolean; shouldPoll?: boolean; visible?: number } | null | undefined;
        if (pollFlags?.aborted || pollFlags?.stale) {
          const spent = Date.now() - tickStarted;
          const delay = Math.max(0, POLL_INTERVAL_MS - spent);
          pollTimerRef.current = setTimeout(() => {
            void tick();
          }, delay);
          return;
        }
        if (!pollFlags?.shouldPoll) {
          stopPolling();
          return;
        }
        const visible = typeof pollFlags.visible === "number" ? pollFlags.visible : 0;
        if (elapsed >= CLIENT_SEARCH_DEADLINE_MS && visible === 0) {
          stopPolling();
          setStatus("failed");
          setMessage(
            tripType === "round_trip"
              ? "We couldn't load these return options. Retry this search or adjust your dates."
              : "We couldn't load flight options in time. Retry this search or adjust your dates.",
          );
          setSearchStillActive(false);
          return;
        }
        if (elapsed >= CLIENT_SEARCH_SETTLE_MS) {
          stopPolling();
          setStatus("ready");
          setMessage("Showing available flights. Some airline responses are still delayed.");
          setSearchStillActive(false);
          return;
        }
        // Compensate: prior loop did await(loadPage) + 200ms, so actual interval was
        // ~RTT+parse+200 (~600–900ms). Target wall cadence ≈ POLL_INTERVAL_MS.
        const spent = Date.now() - tickStarted;
        const delay = Math.max(0, POLL_INTERVAL_MS - spent);
        pollTimerRef.current = setTimeout(() => {
          void tick();
        }, delay);
      };
      pollTimerRef.current = setTimeout(() => {
        void tick();
      }, 0);
    },
    [loadPage, stopPolling, tripType],
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
      dataRef.current = null;
      viewPayloadCacheRef.current.clear();
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

      lastBootstrappedId.current = id;
      // JP-DEEP-CLOSURE-01: start the poll loop immediately. Waiting for an initial
      // loadPage round-trip before schedulePoll serialized ~1 poll interval behind
      // pair persistence once the results shell finally hydrated.
      if (!cancelled) {
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

    // JP-NEXT-PERF-02B: Pair ↔ Segmented is a representation of the SAME search_id.
    // Prefer cached Laravel payload for the other view; never clear READY → full skeleton
    // and never re-init supplier search.
    if (viewChanged) {
      const cached = viewPayloadCacheRef.current.get(representationCacheKey(resolvedSearchId, viewKey));
      if (cached && countVisibleResults(cached) > 0) {
        dataRef.current = cached;
        setData(cached);
        const pipeline = resolvePipelineStatus(cached);
        const nextStatus = mapPipelineToPageStatus(pipeline, cached);
        setStatus(nextStatus === "partial" ? "partial" : "ready");
        setMessage("");
        setPage(1);
        readyRef.current = true;
        // Soft revalidate in background only if search still active — no supplier search.
        if (isActiveSearchStatus(pipeline)) {
          void loadPage(resolvedSearchId, 1, false, "refresh").then((result) => {
            if (result?.shouldPoll) {
              schedulePoll(resolvedSearchId);
            }
          });
        }
        return;
      }
      // Cache miss: keep prior cards mounted while fetching the other representation.
      setStatus((current) => (current === "ready" || current === "partial" ? current : "loading"));
      setMessage("Switching view…");
    } else {
      setStatus((current) => (current === "ready" || current === "partial" ? current : "loading"));
      setMessage("Updating results…");
    }
    // Refresh bumps requestSeq and aborts in-flight polls. If the search is still
    // active, we MUST restart polling — otherwise Pair stays on infinite skeleton.
    void loadPage(resolvedSearchId, 1, false, "refresh").then((result) => {
      if (result?.shouldPoll) {
        schedulePoll(resolvedSearchId);
      }
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filtersKey, laravelSort, resolvedSearchId, viewKey]);

  // Prefetch the alternate Return representation after first READY (same search_id).
  useEffect(() => {
    if (!resolvedSearchId) return;
    if (status !== "ready" && status !== "partial") return;
    if (tripType !== "round_trip") return;
    const current = (viewKey || "pair").toLowerCase();
    const alt = current === "segmented" || current === "split" ? "pair" : "segmented";
    const altKey = representationCacheKey(resolvedSearchId, alt);
    if (viewPayloadCacheRef.current.has(altKey)) return;
    let cancelled = false;
    const controller = new AbortController();
    void (async () => {
      const response = await fetchFlightResultsData({
        searchId: resolvedSearchId,
        page: 1,
        perPage: 12,
        sort: laravelSort,
        filters,
        view: alt,
        signal: controller.signal,
      });
      if (cancelled || !response.ok) return;
      if (countVisibleResults(response.data) > 0) {
        viewPayloadCacheRef.current.set(altKey, response.data);
      }
    })();
    return () => {
      cancelled = true;
      controller.abort();
    };
  }, [filters, laravelSort, representationCacheKey, resolvedSearchId, status, tripType, viewKey]);

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
