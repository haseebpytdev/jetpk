"use client";

import { useCallback, useEffect, useState } from "react";
import { fetchGroupSearchFacets } from "../services/group-ticketing-api";
import type { GroupSearchFacetsLoadState, GroupSearchFacetsResponse, GroupSearchFacetOption } from "../types";

declare global {
  interface Window {
    __jpResetGroupSearchFacetsCache?: () => void;
  }
}

type UseGroupSearchFacetsResult = {
  state: GroupSearchFacetsLoadState;
  sectors: GroupSearchFacetOption[];
  categories: GroupSearchFacetOption[];
  dateBounds: GroupSearchFacetsResponse["date_bounds"];
  errorMessage: string | null;
  retry: () => void;
};

let cachedFacets: GroupSearchFacetsResponse | null = null;
let inflightRequest: Promise<Awaited<ReturnType<typeof fetchGroupSearchFacets>>> | null = null;

function deriveState(data: GroupSearchFacetsResponse): GroupSearchFacetsLoadState {
  return data.sectors.length === 0 ? "empty" : "loaded";
}

async function requestFacets(force = false) {
  if (!force && cachedFacets) {
    return { ok: true as const, data: cachedFacets };
  }

  if (!force && inflightRequest) {
    return inflightRequest;
  }

  inflightRequest = fetchGroupSearchFacets().then((response) => {
    inflightRequest = null;
    if (response.ok) {
      cachedFacets = response.data;
    }
    return response;
  });

  return inflightRequest;
}

export function useGroupSearchFacets(enabled = true): UseGroupSearchFacetsResult {
  const [state, setState] = useState<GroupSearchFacetsLoadState>(enabled ? "loading" : "loaded");
  const [sectors, setSectors] = useState<GroupSearchFacetOption[]>(cachedFacets?.sectors ?? []);
  const [categories, setCategories] = useState<GroupSearchFacetOption[]>(cachedFacets?.categories ?? []);
  const [dateBounds, setDateBounds] = useState<GroupSearchFacetsResponse["date_bounds"]>(cachedFacets?.date_bounds ?? null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const load = useCallback(async (force = false) => {
    if (!enabled) return;

    if (!force && cachedFacets) {
      setState(deriveState(cachedFacets));
      setSectors(cachedFacets.sectors);
      setCategories(cachedFacets.categories);
      setDateBounds(cachedFacets.date_bounds);
      setErrorMessage(null);
      return;
    }

    setState("loading");
    setErrorMessage(null);

    const response = await requestFacets(force);
    if (!response.ok) {
      setState("error");
      setErrorMessage(response.message);
      return;
    }

    setSectors(response.data.sectors);
    setCategories(response.data.categories);
    setDateBounds(response.data.date_bounds);
    setState(deriveState(response.data));
  }, [enabled]);

  useEffect(() => {
    void load(false);
  }, [load]);

  const retry = useCallback(() => {
    cachedFacets = null;
    void load(true);
  }, [load]);

  return { state, sectors, categories, dateBounds, errorMessage, retry };
}

/** Test helper to reset module cache between Playwright runs. */
export function resetGroupSearchFacetsCacheForTests(): void {
  cachedFacets = null;
  inflightRequest = null;
}

if (typeof window !== "undefined") {
  window.__jpResetGroupSearchFacetsCache = resetGroupSearchFacetsCacheForTests;
}
