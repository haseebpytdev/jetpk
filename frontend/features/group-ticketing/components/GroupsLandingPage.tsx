"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { useGroupSearchFacets } from "../hooks/use-group-search-facets";
import { validateGroupSearch } from "@/features/search/utils/validation";
import { GroupTicketingForm } from "@/features/search/components/GroupTicketingForm";
import { GroupCategoryCards } from "./GroupCategoryCards";
import { laravelApiPath } from "@/services/flight-search";
import type { GroupSearchFacetOption } from "../types";

type GroupsLandingCms = {
  hero?: { kicker?: string; title?: string; description?: string };
  categories?: { kicker?: string; title?: string; description?: string };
};

const DEFAULT_CMS: Required<GroupsLandingCms> = {
  hero: {
    kicker: "GROUP TRAVEL MADE SIMPLE",
    title: "Find better group fares for your journey",
    description:
      "Search live block-seat inventory by airline, sector, and departure — transparent per-seat pricing before you book.",
  },
  categories: {
    kicker: "Explore Group Travel Packages",
    title: "Browse available group corridors",
    description: "Open All Groups or a live inventory category to refine results.",
  },
};

/**
 * /groups discovery landing — Airline | Sector | Date search + API category tiles.
 */
export function GroupsLandingPage() {
  const router = useRouter();
  const facets = useGroupSearchFacets();
  const [cms, setCms] = useState(DEFAULT_CMS);
  const [airline, setAirline] = useState("");
  const [sector, setSector] = useState("");
  const [travelDate, setTravelDate] = useState("");
  const [errors, setErrors] = useState<string[]>([]);
  const [searching, setSearching] = useState(false);

  const sectorValues = useMemo(() => facets.sectors.map((item) => item.value), [facets.sectors]);
  const airlineValues = useMemo(() => facets.airlines.map((item) => item.value), [facets.airlines]);

  const categoryCards = useMemo((): GroupSearchFacetOption[] => {
    if (facets.tiles.length > 0) {
      return facets.tiles.map((tile) => ({
        value: tile.slug ?? tile.key,
        label: tile.title,
        inventory_count: tile.package_count,
        image_url: tile.image_url ?? null,
        href: tile.url,
      }));
    }

    return facets.categories;
  }, [facets.tiles, facets.categories]);

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

  const pushSearch = useCallback(() => {
    const result = validateGroupSearch(
      { airline, sector, travelDate },
      { sectorValues, airlineValues },
    );
    if (!result.valid) {
      setErrors(result.errors);
      setSearching(false);
      return;
    }
    setErrors([]);
    setSearching(true);
    const next = new URLSearchParams();
    if (airline.trim()) next.set("airline", airline.trim());
    if (sector) next.set("sector", sector);
    if (travelDate) next.set("date_from", travelDate);
    router.push(`/groups/search?${next.toString()}`);
  }, [airline, sector, travelDate, sectorValues, airlineValues, router]);

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
            <GroupTicketingForm
              airline={airline}
              sector={sector}
              travelDate={travelDate}
              facetsState={facets.state}
              airlines={facets.airlines}
              sectors={facets.sectors}
              dateBounds={facets.dateBounds}
              facetsError={facets.errorMessage}
              onRetryFacets={facets.retry}
              onAirlineChange={setAirline}
              onSectorChange={setSector}
              onTravelDateChange={setTravelDate}
              onSubmit={pushSearch}
              errors={errors}
              disabled={searching}
            />
          </div>
        </div>
      </section>

      <section className="mx-auto w-full max-w-jp-container px-jp-xl py-12 sm:py-16" data-testid="groups-landing-categories">
        <div className="mb-6 max-w-2xl">
          {cms.categories.kicker ? (
            <p className="text-jp-xs font-semibold uppercase tracking-[0.14em] text-jp-muted">{cms.categories.kicker}</p>
          ) : null}
          <h2 className="mt-1 text-2xl font-semibold tracking-[-0.03em] text-jp-text sm:text-3xl">{cms.categories.title}</h2>
          {cms.categories.description ? (
            <p className="mt-2 text-jp-sm text-jp-muted">{cms.categories.description}</p>
          ) : null}
        </div>
        <GroupCategoryCards
          categories={categoryCards}
          mode="link"
          variant="media"
          disabled={facets.state !== "loaded"}
        />
      </section>
    </div>
  );
}
