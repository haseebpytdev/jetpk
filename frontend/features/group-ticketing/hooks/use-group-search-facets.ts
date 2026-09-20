"use client";

import { useCallback, useEffect, useState } from "react";
import { fetchGroupSearchFacets } from "../services/group-ticketing-api";
import type {
  GroupDiscoveryTile,
  GroupSearchFacetsLoadState,
  GroupSearchFacetsResponse,
  GroupSearchFacetOption,
} from "../types";

declare global {
  interface Window {
    __jpResetGroupSearchFacetsCache?: () => void;
  }
}

type UseGroupSearchFacetsResult = {
  state: GroupSearchFacetsLoadState;
  airlines: GroupSearchFacetOption[];
  sectors: GroupSearchFacetOption[];
  categories: GroupSearchFacetOption[];
  tiles: GroupDiscoveryTile[];
  dateBounds: GroupSearchFacetsResponse["date_bounds"];
  errorMessage: string | null;
  retry: () => void;
};

let cachedFacets: GroupSearchFacetsResponse | null = null;
let inflightRequest: Promise<Awaited<ReturnType<typeof fetchGroupSearchFacets>>> | null = null;

function normalizeFacets(data: GroupSearchFacetsResponse): GroupSearchFacetsResponse {
  return {
    airlines: data.airlines ?? [],
    sectors: data.sectors ?? [],
    categories: data.categories ?? [],
    tiles: data.tiles ?? [],
    date_bounds: data.date_bounds ?? null,
  };
}

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
      cachedFacets = normalizeFacets(response.data);
    }
    return response.ok
      ? { ok: true as const, data: cachedFacets as GroupSearchFacetsResponse }
      : response;
  });

  return inflightRequest;
}

export function useGroupSearchFacets(enabled = true): UseGroupSearchFacetsResult {
  const [state, setState] = useState<GroupSearchFacetsLoadState>(enabled ? "loading" : "loaded");
  const [airlines, setAirlines] = useState<GroupSearchFacetOption[]>(cachedFacets?.airlines ?? []);
  const [sectors, setSectors] = useState<GroupSearchFacetOption[]>(cachedFacets?.sectors ?? []);
  const [categories, setCategories] = useState<GroupSearchFacetOption[]>(cachedFacets?.categories ?? []);
  const [tiles, setTiles] = useState<GroupDiscoveryTile[]>(cachedFacets?.tiles ?? []);
  const [dateBounds, setDateBounds] = useState<GroupSearchFacetsResponse["date_bounds"]>(
    cachedFacets?.date_bounds ?? null,
  );
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const applyData = useCallback((data: GroupSearchFacetsResponse) => {
    const normalized = normalizeFacets(data);
    setAirlines(normalized.airlines);
    setSectors(normalized.sectors);
    setCategories(normalized.categories);
    setTiles(normalized.tiles ?? []);
    setDateBounds(normalized.date_bounds);
    setState(deriveState(normalized));
    setErrorMessage(null);
  }, []);

  const load = useCallback(
    async (force = false) => {
      if (!enabled) return;

      if (!force && cachedFacets) {
        applyData(cachedFacets);
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

      applyData(response.data);
    },
    [enabled, applyData],
  );

  useEffect(() => {
    void load(false);
  }, [load]);

  const retry = useCallback(() => {
    cachedFacets = null;
    void load(true);
  }, [load]);

  return { state, airlines, sectors, categories, tiles, dateBounds, errorMessage, retry };
}

/** Test helper to reset module cache between Playwright runs. */
export function resetGroupSearchFacetsCacheForTests(): void {
  cachedFacets = null;
  inflightRequest = null;
}

if (typeof window !== "undefined") {
  window.__jpResetGroupSearchFacetsCache = resetGroupSearchFacetsCacheForTests;
}
