"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { seedGroupSearchFacetsCache, useGroupSearchFacets } from "../hooks/use-group-search-facets";
import { validateGroupSearch } from "@/features/search/utils/validation";
import { fetchGroupResultsPage, fetchGroupSearchData } from "../services/group-ticketing-api";
import type {
  GroupPackage,
  GroupSearchDataResponse,
  GroupSearchFacetsResponse,
  GroupSearchFilters,
} from "../types";
import { GroupEmptyResultsState } from "./GroupStateCards";
import { GroupLockedState } from "./GroupStateCards";
import { GroupResultCard } from "./GroupResultCard";
import { SharedGroupSearch } from "./SharedGroupSearch";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { laravelApiPath } from "@/services/flight-search";

function parseFilters(params: URLSearchParams): GroupSearchFilters {
  return {
    airline: params.get("airline") ?? undefined,
    sector: params.get("sector") ?? undefined,
    date_from: params.get("date_from") ?? undefined,
    category: params.get("category") ?? undefined,
    page: Number(params.get("page") ?? "1") || 1,
    sort: params.get("sort") ?? undefined,
  };
}

function filtersKey(filters: GroupSearchFilters): string {
  return [
    filters.airline ?? "",
    filters.sector ?? "",
    filters.date_from ?? "",
    filters.category ?? "",
    String(filters.page ?? 1),
    filters.sort ?? "",
  ].join("|");
}

type GroupSearchCmsHero = {
  kicker?: string;
  title?: string;
  description?: string;
};

type GroupSearchPageProps = {
  initialFilters?: GroupSearchFilters;
  initialResults?: GroupSearchDataResponse | null;
  initialFacets?: GroupSearchFacetsResponse | null;
};

export function GroupSearchPage({
  initialFilters,
  initialResults = null,
  initialFacets = null,
}: GroupSearchPageProps = {}) {
  const router = useRouter();
  const searchParams = useSearchParams();
  const params = useMemo(() => new URLSearchParams(searchParams.toString()), [searchParams]);
  const filters = useMemo(() => parseFilters(params), [params]);
  const hasSearch = Boolean(filters.airline || filters.sector || filters.date_from || filters.category);
  const currentKey = filtersKey(filters);
  const initialKey = initialFilters ? filtersKey(initialFilters) : null;
  const ssrMatches = Boolean(initialResults && initialKey && initialKey === currentKey);

  // Seed facets cache once before the hook reads it (module-level, safe across remounts).
  if (initialFacets) {
    seedGroupSearchFacetsCache(initialFacets);
  }

  const facets = useGroupSearchFacets();
  const airlineValues = useMemo(() => facets.airlines.map((item) => item.value), [facets.airlines]);
  const sectorValues = useMemo(() => facets.sectors.map((item) => item.value), [facets.sectors]);
  const categoryValues = useMemo(() => facets.categories.map((item) => item.value), [facets.categories]);

  const [airline, setAirline] = useState(filters.airline ?? "");
  const [sector, setSector] = useState(filters.sector ?? "");
  const [category, setCategory] = useState(filters.category ?? "all");
  const [travelDate, setTravelDate] = useState(filters.date_from ?? "");
  const [errors, setErrors] = useState<string[]>([]);
  const [loading, setLoading] = useState(hasSearch && !ssrMatches);
  const [cards, setCards] = useState<GroupPackage[]>(ssrMatches ? initialResults!.cards : []);
  const [countLabel, setCountLabel] = useState(ssrMatches ? initialResults!.count_label : "");
  const [statusMessage, setStatusMessage] = useState<string | null>(
    ssrMatches ? (initialResults!.status_message ?? null) : null,
  );
  const [userNotice, setUserNotice] = useState<string | null>(
    ssrMatches ? (initialResults!.user_notice ?? null) : null,
  );
  const [locked, setLocked] = useState(ssrMatches ? initialResults!.lock_state.locked : false);
  const [lockedMessage, setLockedMessage] = useState<string | undefined>(
    ssrMatches ? (initialResults!.lock_state.message ?? undefined) : undefined,
  );
  const [page, setPage] = useState(ssrMatches ? initialResults!.page : (filters.page ?? 1));
  const [hasMore, setHasMore] = useState(ssrMatches ? initialResults!.has_more : false);
  const [cmsHero, setCmsHero] = useState<GroupSearchCmsHero>({
    kicker: "Group travel",
    title: "Search group departures",
    description: "Find block-seat group inventory with transparent per-seat pricing.",
  });
  const abortRef = useRef<AbortController | null>(null);
  const requestSeq = useRef(0);
  const hydratedSsrKey = useRef<string | null>(ssrMatches ? currentKey : null);

  useEffect(() => {
    let cancelled = false;
    void (async () => {
      try {
        const response = await fetch(laravelApiPath("/api/public/content/pages/group-search"), {
          credentials: "same-origin",
          headers: { Accept: "application/json" },
        });
        if (!response.ok) return;
        const payload = (await response.json()) as { content?: { hero?: GroupSearchCmsHero } };
        const hero = payload.content?.hero;
        if (!cancelled && hero) {
          setCmsHero({
            kicker: hero.kicker || cmsHero.kicker,
            title: hero.title || cmsHero.title,
            description: hero.description || cmsHero.description,
          });
        }
      } catch {
        // Keep bootstrap defaults when CMS is unavailable.
      }
    })();
    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps -- hydrate CMS once on mount
  }, []);

  const staleFacetErrors = useMemo(() => {
    if (facets.state !== "loaded") return [];
    const next: string[] = [];
    if (filters.airline && !airlineValues.includes(filters.airline)) {
      next.push("Selected airline is no longer available. Please choose again.");
    }
    if (filters.sector && !sectorValues.includes(filters.sector)) {
      next.push("Selected sector is no longer available. Please choose again.");
    }
    return next;
  }, [facets.state, filters.airline, filters.sector, airlineValues, sectorValues]);

  const formErrors = useMemo(() => [...staleFacetErrors, ...errors], [staleFacetErrors, errors]);

  useEffect(() => {
    if (facets.state !== "loaded") return;

    setAirline(
      filters.airline && airlineValues.includes(filters.airline) ? filters.airline : "",
    );
    setSector(filters.sector && sectorValues.includes(filters.sector) ? filters.sector : "");
    setCategory(
      filters.category && categoryValues.includes(filters.category) ? filters.category : "all",
    );
    setTravelDate(filters.date_from ?? "");
  }, [
    facets.state,
    filters.airline,
    filters.sector,
    filters.category,
    filters.date_from,
    airlineValues,
    sectorValues,
    categoryValues,
  ]);

  const filtersValid = useMemo(() => {
    if (!hasSearch) {
      return false;
    }
    // URL-driven searches must load results without waiting on facet hydration.
    // Facet stale-value checks apply only after facets are loaded.
    if (facets.state !== "loaded") {
      return true;
    }
    if (filters.airline && airlineValues.length > 0 && !airlineValues.includes(filters.airline)) {
      return false;
    }
    if (filters.sector && sectorValues.length > 0 && !sectorValues.includes(filters.sector)) {
      return false;
    }
    if (filters.category && categoryValues.length > 0 && !categoryValues.includes(filters.category)) {
      return false;
    }
    return true;
  }, [hasSearch, facets.state, filters.airline, filters.sector, filters.category, airlineValues, sectorValues, categoryValues]);

  const applyResults = useCallback((response: GroupSearchDataResponse, append = false) => {
    setCards((current) => (append ? [...current, ...response.cards] : response.cards));
    setCountLabel(response.count_label);
    setStatusMessage(response.status_message ?? null);
    setUserNotice(response.user_notice ?? null);
    setLocked(response.lock_state.locked);
    setLockedMessage(response.lock_state.message ?? undefined);
    setPage(response.page);
    setHasMore(response.has_more);
  }, []);

  const loadResults = useCallback(async (nextFilters: GroupSearchFilters, append = false) => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;
    const seq = ++requestSeq.current;

    // Secondary loading indicator only — never wipe cards before replacement arrives.
    setLoading(true);

    const response = await fetchGroupSearchData(nextFilters);

    if (seq !== requestSeq.current || controller.signal.aborted) {
      return;
    }

    if (!response.ok) {
      setErrors([response.message]);
      setLoading(false);
      return;
    }

    applyResults(response.data, append);
    setLoading(false);
  }, [applyResults]);

  useEffect(() => {
    if (!hasSearch || !filtersValid) return;

    // Authoritative SSR payload already rendered for this exact filter key.
    if (hydratedSsrKey.current === currentKey) {
      hydratedSsrKey.current = null;
      setLoading(false);
      return;
    }

    void loadResults(filters, false);
    return () => {
      abortRef.current?.abort();
    };
  }, [filters, hasSearch, filtersValid, loadResults, currentKey]);

  const pushFilters = (nextValues: {
    airline: string;
    sector: string;
    category: string;
    travelDate: string;
  }) => {
    const result = validateGroupSearch(nextValues, {
      airlineValues,
      sectorValues,
      categoryValues,
    });
    if (!result.valid) {
      setErrors(result.errors);
      return;
    }

    const next = new URLSearchParams();
    if (nextValues.airline) next.set("airline", nextValues.airline);
    if (nextValues.sector) next.set("sector", nextValues.sector);
    if (nextValues.travelDate) next.set("date_from", nextValues.travelDate);
    if (nextValues.category && nextValues.category !== "all") next.set("category", nextValues.category);
    router.push(`/groups/search?${next.toString()}`);
  };

  const handleSubmit = () => {
    pushFilters({ airline, sector, category, travelDate });
  };

  const handleClear = () => {
    setAirline("");
    setSector("");
    setCategory("all");
    setTravelDate("");
    setErrors([]);
    setCards([]);
    setCountLabel("");
    setStatusMessage(null);
    setUserNotice(null);
    router.push("/groups/search");
  };

  const handleLoadMore = async () => {
    const nextPage = page + 1;
    const response = await fetchGroupResultsPage({ ...filters, page: nextPage });
    if (!response.ok) {
      setErrors([response.message]);
      return;
    }
    setCards((current) => [...current, ...response.data.cards]);
    setPage(response.data.page);
    setHasMore(response.data.has_more);
    setCountLabel(response.data.count_label);
  };

  if (locked) {
    return (
      <div className="ota-container py-8">
        <GroupLockedState message={lockedMessage} />
      </div>
    );
  }

  return (
    <div className="mx-auto w-full max-w-jp-container px-jp-xl py-8 font-[Inter,system-ui,sans-serif]" data-testid="group-search-page">
      <header className="mb-6" data-testid="group-search-cms-hero">
        {cmsHero.kicker ? (
          <p className="text-jp-xs font-semibold uppercase tracking-wide text-jp-primary">{cmsHero.kicker}</p>
        ) : null}
        <h1 className="text-2xl font-semibold text-jp-text">{cmsHero.title}</h1>
        {cmsHero.description ? <p className="mt-1 text-jp-sm text-jp-muted">{cmsHero.description}</p> : null}
        {filters.category ? (
          <p className="mt-2 text-jp-sm text-jp-text" data-testid="group-active-category">
            Category: <span className="font-semibold">{filters.category}</span>
          </p>
        ) : null}
      </header>

      <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 shadow-jp-sm">
        <SharedGroupSearch
          values={{ airline, sector, category, travelDate }}
          facetsState={facets.state}
          airlines={facets.airlines}
          sectors={facets.sectors}
          categories={facets.categories}
          dateBounds={facets.dateBounds}
          facetsError={facets.errorMessage}
          onRetryFacets={facets.retry}
          onChange={(next) => {
            setAirline(next.airline);
            setSector(next.sector);
            setCategory(next.category);
            setTravelDate(next.travelDate);
          }}
          onSubmit={handleSubmit}
          onClear={handleClear}
          errors={formErrors}
          disabled={false}
          showCategoryCards={false}
        />
      </div>

      {hasSearch ? (
        <section className="mt-8 space-y-4" aria-live="polite">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-lg font-semibold text-jp-text">Results</h2>
            <p className="text-jp-sm text-jp-muted" data-testid="group-result-count">
              {loading && cards.length === 0 ? "Loading results…" : countLabel}
              {hasMore ? " · more available" : ""}
            </p>
          </div>
          {userNotice ? (
            <p className="rounded-jp-md border border-amber-200 bg-amber-50 px-3 py-2 text-jp-sm text-amber-900">{userNotice}</p>
          ) : null}
          {statusMessage && cards.length === 0 && !loading ? <GroupEmptyResultsState /> : null}
          {cards.map((card) => (
            <GroupResultCard key={`${card.public_id ?? card.id}`} card={card} />
          ))}
          {hasMore ? (
            <div className="flex justify-center">
              <PrimaryButton onClick={() => void handleLoadMore()} disabled={loading} data-testid="group-load-more">
                Load more
              </PrimaryButton>
            </div>
          ) : null}
        </section>
      ) : null}
    </div>
  );
}
