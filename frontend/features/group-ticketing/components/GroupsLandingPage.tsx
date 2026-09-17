"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { useGroupSearchFacets } from "../hooks/use-group-search-facets";
import { validateGroupSearch } from "@/features/search/utils/validation";
import { SharedGroupSearch } from "./SharedGroupSearch";
import { GroupCategoryCards } from "./GroupCategoryCards";
import { buildGroupHandoffQuery, laravelApiPath } from "@/services/flight-search";
import { buildGroupSearchPagePath } from "@/features/search/utils/laravel-payload";

type GroupsLandingCms = {
  hero?: { kicker?: string; title?: string; description?: string };
  categories?: { kicker?: string; title?: string; description?: string };
};

const DEFAULT_CMS: Required<GroupsLandingCms> = {
  hero: {
    kicker: "GROUP TRAVEL MADE SIMPLE",
    title: "Find better group fares for your journey",
    description:
      "Search live block-seat inventory by route, airline, and departure — transparent per-seat pricing before you book.",
  },
  categories: {
    kicker: "Explore destinations",
    title: "Browse by group category",
    description: "Jump into UAE, KSA, and other available group corridors.",
  },
};

function formatSectorLabel(sector: string): string {
  const parts = sector.split("-").map((p) => p.trim()).filter(Boolean);
  if (parts.length === 2) {
    return `${parts[0]} → ${parts[1]}`;
  }
  return sector;
}

/**
 * /groups discovery landing — visual hero with floating search, live route chips, categories.
 * Results live on /groups/search.
 */
export function GroupsLandingPage() {
  const router = useRouter();
  const facets = useGroupSearchFacets();
  const [cms, setCms] = useState(DEFAULT_CMS);
  const [airline, setAirline] = useState("");
  const [sector, setSector] = useState("");
  const [category, setCategory] = useState("all");
  const [travelDate, setTravelDate] = useState("");
  const [errors, setErrors] = useState<string[]>([]);
  const [searching, setSearching] = useState(false);
  const [searchPhase, setSearchPhase] = useState<"idle" | "searching" | "seats" | "departures">("idle");

  const airlineValues = useMemo(() => facets.airlines.map((item) => item.value), [facets.airlines]);
  const sectorValues = useMemo(() => facets.sectors.map((item) => item.value), [facets.sectors]);
  const categoryValues = useMemo(() => facets.categories.map((item) => item.value), [facets.categories]);
  const popularSectors = useMemo(() => facets.sectors.slice(0, 6), [facets.sectors]);
  const dateWindowLabel = useMemo(() => {
    const min = facets.dateBounds?.minimum;
    const max = facets.dateBounds?.maximum;
    if (min && max) return `${min} → ${max}`;
    if (min) return `from ${min}`;
    return null;
  }, [facets.dateBounds]);

  useEffect(() => {
    let cancelled = false;
    void (async () => {
      try {
        const response = await fetch(laravelApiPath("/api/public/content/pages/group-search"), {
          credentials: "same-origin",
          headers: { Accept: "application/json" },
        });
        if (!response.ok) return;
        const payload = (await response.json()) as { content?: GroupsLandingCms };
        const content = payload.content;
        if (cancelled || !content) return;
        setCms({
          hero: {
            kicker: content.hero?.kicker || DEFAULT_CMS.hero.kicker,
            title: content.hero?.title || DEFAULT_CMS.hero.title,
            description: content.hero?.description || DEFAULT_CMS.hero.description,
          },
          categories: {
            kicker: content.categories?.kicker || DEFAULT_CMS.categories.kicker,
            title: content.categories?.title || DEFAULT_CMS.categories.title,
            description: content.categories?.description || DEFAULT_CMS.categories.description,
          },
        });
      } catch {
        // Keep defaults when CMS unavailable.
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const pushSearch = useCallback(
    (next: { airline: string; sector: string; category: string; travelDate: string }) => {
      const result = validateGroupSearch(next, {
        airlineValues,
        sectorValues,
        categoryValues,
      });
      if (!result.valid) {
        setErrors(result.errors);
        setSearching(false);
        setSearchPhase("idle");
        return;
      }
      setErrors([]);
      // Immediate navigation ack — do not pad with artificial setTimeout delays.
      setSearching(true);
      setSearchPhase("searching");
      router.push(buildGroupSearchPagePath(buildGroupHandoffQuery(next)));
    },
    [airlineValues, sectorValues, categoryValues, router],
  );

  const searchStatusLabel =
    searchPhase === "seats"
      ? "Checking seats…"
      : searchPhase === "departures"
        ? "Finding matching departures…"
        : searching
          ? "Searching available groups…"
          : null;

  return (
    <div data-testid="groups-landing-page">
      <section
        className="relative overflow-hidden border-b border-jp-border"
        data-testid="groups-landing-hero"
        aria-labelledby="groups-hero-heading"
      >
        <div
          className="absolute inset-0 bg-gradient-to-br from-[#0f3d2e] via-[#1a5c46] to-jp-page"
          aria-hidden="true"
        />
        <div
          className="pointer-events-none absolute inset-0 opacity-40"
          aria-hidden="true"
          style={{
            backgroundImage:
              "radial-gradient(circle at 78% 18%, rgba(255,255,255,0.18), transparent 40%), radial-gradient(circle at 12% 78%, rgba(15,118,110,0.35), transparent 36%)",
          }}
        />
        <svg
          className="pointer-events-none absolute right-6 top-10 hidden h-28 w-48 text-white/25 lg:block"
          viewBox="0 0 192 112"
          fill="none"
          aria-hidden="true"
        >
          <path d="M12 78c36-8 58-40 96-48 24-5 48-2 72 16" stroke="currentColor" strokeWidth="1.5" strokeDasharray="4 6" strokeLinecap="round" />
          <circle cx="176" cy="48" r="3.5" fill="currentColor" />
        </svg>

        <div className="relative mx-auto w-full max-w-jp-container px-jp-xl pb-14 pt-10 sm:pb-16 sm:pt-12">
          <div className="max-w-2xl text-white" data-testid="groups-landing-cms-hero">
            {cms.hero.kicker ? (
              <p className="text-jp-xs font-semibold uppercase tracking-[0.16em] text-white/80">{cms.hero.kicker}</p>
            ) : null}
            <h1
              id="groups-hero-heading"
              className="mt-2 text-3xl font-semibold tracking-[-0.035em] text-white sm:text-4xl lg:text-5xl"
            >
              {cms.hero.title}
            </h1>
            {cms.hero.description ? (
              <p className="mt-3 max-w-xl text-jp-sm leading-relaxed text-white/90 sm:text-base">{cms.hero.description}</p>
            ) : null}
          </div>

          <div
            className="relative z-10 mt-8 rounded-jp-xl border border-white/20 bg-jp-surface p-4 shadow-jp-md sm:-mb-10 sm:mt-10 sm:p-6"
            data-testid="groups-landing-search"
          >
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
              <p className="text-jp-sm font-semibold text-jp-text">Search group fares</p>
              {searchStatusLabel ? (
                <p className="text-jp-xs font-medium text-jp-primary" role="status" aria-live="polite" data-testid="groups-search-progress">
                  {searchStatusLabel}
                </p>
              ) : null}
            </div>
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
              onSubmit={() => pushSearch({ airline, sector, category, travelDate })}
              onClear={() => {
                setAirline("");
                setSector("");
                setCategory("all");
                setTravelDate("");
                setErrors([]);
                setSearching(false);
                setSearchPhase("idle");
              }}
              errors={errors}
              showCategoryCards={false}
            />
          </div>
        </div>
      </section>

      <div className="mx-auto w-full max-w-jp-container px-jp-xl pb-12 pt-12 sm:pt-16">
        {facets.state === "loaded" && popularSectors.length > 0 ? (
          <section className="mb-12" aria-labelledby="groups-popular-routes-heading" data-testid="groups-landing-popular-routes">
            <p className="text-jp-xs font-semibold uppercase tracking-[0.14em] text-jp-primary">Available corridors</p>
            <h2 id="groups-popular-routes-heading" className="mt-1 text-xl font-semibold tracking-[-0.02em] text-jp-text sm:text-2xl">
              Popular group routes
            </h2>
            <p className="mt-1 max-w-2xl text-jp-sm text-jp-muted">Live sectors from current inventory — tap to fill search.</p>
            <div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
              {popularSectors.map((item) => (
                <button
                  key={item.value}
                  type="button"
                  data-testid={`groups-route-chip-${item.value}`}
                  onClick={() => {
                    setSector(item.value);
                    setErrors([]);
                  }}
                  className="flex min-h-jp-tap flex-col items-start rounded-jp-lg border border-jp-border bg-jp-surface px-3.5 py-3 text-left shadow-jp-sm transition hover:border-jp-primary hover:shadow-md focus-visible:outline-none focus-visible:shadow-jp-focus"
                >
                  <span className="text-jp-sm font-semibold text-jp-text">{formatSectorLabel(item.label || item.value)}</span>
                  {typeof item.inventory_count === "number" ? (
                    <span className="mt-1 text-jp-xs text-jp-muted">
                      {item.inventory_count} departure{item.inventory_count === 1 ? "" : "s"}
                    </span>
                  ) : (
                    <span className="mt-1 text-jp-xs text-jp-muted">Live group corridor</span>
                  )}
                </button>
              ))}
            </div>
            {dateWindowLabel ? (
              <p className="mt-3 text-jp-xs text-jp-muted" data-testid="groups-upcoming-dates">
                Inventory departure window: {dateWindowLabel}
              </p>
            ) : null}
          </section>
        ) : null}

        <section className="mb-12 grid gap-3 sm:grid-cols-3" data-testid="groups-landing-how-it-works" aria-label="How group booking works">
          {[
            { title: "Search live inventory", body: "Filter by sector, airline, and departure from published group stock." },
            { title: "Review seat pricing", body: "See transparent per-seat fares before you start a booking request." },
            { title: "Confirm before payment", body: "Availability and fare are confirmed before any payment step." },
          ].map((step) => (
            <article key={step.title} className="rounded-jp-xl border border-jp-border bg-jp-surface p-4 shadow-jp-sm">
              <h3 className="text-jp-sm font-semibold text-jp-text">{step.title}</h3>
              <p className="mt-1 text-jp-xs leading-relaxed text-jp-muted">{step.body}</p>
            </article>
          ))}
        </section>

        <section aria-labelledby="groups-categories-heading" data-testid="groups-landing-categories">
          {cms.categories.kicker ? (
            <p className="text-jp-xs font-semibold uppercase tracking-[0.14em] text-jp-primary">{cms.categories.kicker}</p>
          ) : null}
          <h2 id="groups-categories-heading" className="mt-1 text-2xl font-semibold tracking-[-0.02em] text-jp-text">
            {cms.categories.title}
          </h2>
          {cms.categories.description ? (
            <p className="mt-1 max-w-2xl text-jp-sm leading-relaxed text-jp-muted">{cms.categories.description}</p>
          ) : null}

          <GroupCategoryCards
            categories={facets.categories}
            mode="link"
            variant="media"
            className="mt-5"
            disabled={facets.state !== "loaded"}
          />
        </section>
      </div>
    </div>
  );
}
