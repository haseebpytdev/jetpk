"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { initFlightSearch } from "@/services/flight-search";
import { fetchFlightResultsData } from "../services/flight-results-api";
import type { ActiveResultsFilters, FlightResultsDataResponse, ResultsPageStatus } from "../types";
import { resolveLaravelSort, type UiSortKey } from "../utils/sorting";
import { criteriaFromSearchParams } from "../utils/criteria-from-params";
import { searchIdentityKey } from "../utils/search-identity";

export type UseFlightResultsOptions = {
  searchId: string | null;
  searchParams: URLSearchParams;
  sort: UiSortKey;
  filters: ActiveResultsFilters;
  view?: string | null;
};

function isAuthoritativeEmpty(payload: FlightResultsDataResponse): boolean {
  const status = (payload.status ?? payload.search_freshness?.status ?? "").toLowerCase();
  if (status === "searching" || status === "partial" || status === "in_progress") {
    return false;
  }
  const total = payload.total ?? 0;
  const count = (payload.offers ?? []).length + (payload.outbound_options ?? []).length + (payload.paired_options ?? []).length;
  return total === 0 && count === 0;
}

export function useFlightResults({ searchId, searchParams, sort, filters, view }: UseFlightResultsOptions) {
  const [resolvedSearchId, setResolvedSearchId] = useState<string | null>(searchId);
  const [status, setStatus] = useState<ResultsPageStatus>("idle");
  const [message, setMessage] = useState("Searching flights…");
  const [data, setData] = useState<FlightResultsDataResponse | null>(null);
  const [page, setPage] = useState(1);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const abortRef = useRef<AbortController | null>(null);
  const requestSeq = useRef(0);
  const readyRef = useRef(false);
  const lastBootstrappedId = useRef<string | null>(null);
  const skipNextRefresh = useRef(true);
  const filtersKey = JSON.stringify(filters);
  const laravelSort = resolveLaravelSort(sort);
  const identity = searchIdentityKey(searchParams);
  const viewKey = view ?? "";

  const loadPage = useCallback(
    async (id: string, targetPage: number, append: boolean, phase: "init" | "refresh") => {
      abortRef.current?.abort();
      const controller = new AbortController();
      abortRef.current = controller;
      const seq = ++requestSeq.current;

      if (!append) {
        setStatus(phase === "init" ? "initializing" : "loading");
        setMessage(phase === "init" ? "Searching flights…" : "Finding the best available flights…");
      } else {
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
        return;
      }

      if (!response.ok) {
        if (response.status === 0 && response.message === "Request cancelled.") {
          return;
        }
        setStatus(response.status === 410 ? "expired" : "error");
        setMessage(response.message);
        setIsLoadingMore(false);
        return;
      }

      const payload = response.data;
      setData((current) => {
        if (!append || !current) return payload;
        return {
          ...payload,
          offers: [...(current.offers ?? []), ...(payload.offers ?? [])],
          outbound_options: [...(current.outbound_options ?? []), ...(payload.outbound_options ?? [])],
          paired_options: [...(current.paired_options ?? []), ...(payload.paired_options ?? [])],
        };
      });

      if (isAuthoritativeEmpty(payload) && !append) {
        setStatus("empty");
        setMessage(payload.empty_message ?? "No flights match your search.");
      } else {
        setStatus("ready");
        setMessage("");
      }
      setPage(targetPage);
      setIsLoadingMore(false);
      readyRef.current = true;
    },
    [filters, laravelSort, viewKey],
  );

  useEffect(() => {
    let cancelled = false;

    const bootstrap = async () => {
      if (searchId && lastBootstrappedId.current === searchId) {
        return;
      }

      setStatus("initializing");
      setMessage("Searching flights…");
      setData(null);
      readyRef.current = false;
      skipNextRefresh.current = true;

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
      await loadPage(id, 1, false, "init");
    };

    void bootstrap();

    return () => {
      cancelled = true;
      abortRef.current?.abort();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps -- bootstrap only when search identity changes
  }, [identity]);

  useEffect(() => {
    if (!resolvedSearchId || !readyRef.current) return;
    if (skipNextRefresh.current) {
      skipNextRefresh.current = false;
      return;
    }
    void loadPage(resolvedSearchId, 1, false, "refresh");
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filtersKey, laravelSort, resolvedSearchId, viewKey]);

  const retry = useCallback(async () => {
    readyRef.current = false;
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
    await loadPage(id, 1, false, "init");
  }, [loadPage, searchParams]);

  const loadMore = useCallback(() => {
    if (!resolvedSearchId || !data?.has_more || isLoadingMore) return;
    void loadPage(resolvedSearchId, page + 1, true, "refresh");
  }, [data?.has_more, isLoadingMore, loadPage, page, resolvedSearchId]);

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
    total: data?.total ?? 0,
    isLoadingMore,
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
