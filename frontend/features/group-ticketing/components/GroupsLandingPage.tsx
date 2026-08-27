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
    kicker: "Group travel",
    title: "JetPakistan Groups",
    description: "Browse block-seat group departures with transparent per-seat pricing.",
  },
  categories: {
    kicker: "Explore group fares",
    title: "Browse group categories",
    description: "Choose a destination category to view live inventory.",
  },
};

/**
 * /groups discovery landing — CMS intro, shared compact search, dynamic categories.
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

  const airlineValues = useMemo(() => facets.airlines.map((item) => item.value), [facets.airlines]);
  const sectorValues = useMemo(() => facets.sectors.map((item) => item.value), [facets.sectors]);
  const categoryValues = useMemo(() => facets.categories.map((item) => item.value), [facets.categories]);

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
        return;
      }
      setErrors([]);
      router.push(buildGroupSearchPagePath(buildGroupHandoffQuery(next)));
    },
    [airlineValues, sectorValues, categoryValues, router],
  );

  return (
    <div className="mx-auto w-full max-w-jp-container px-jp-xl py-8 font-[Inter,system-ui,sans-serif]" data-testid="groups-landing-page">
      <header className="mb-6" data-testid="groups-landing-cms-hero">
        {cms.hero.kicker ? (
          <p className="text-jp-xs font-semibold uppercase tracking-wide text-jp-primary">{cms.hero.kicker}</p>
        ) : null}
        <h1 className="text-2xl font-semibold text-jp-text sm:text-3xl">{cms.hero.title}</h1>
        {cms.hero.description ? <p className="mt-2 max-w-2xl text-jp-sm text-jp-muted">{cms.hero.description}</p> : null}
      </header>

      <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 shadow-jp-sm" data-testid="groups-landing-search">
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
          }}
          errors={errors}
          showCategoryCards={false}
        />
      </div>

      <section className="mt-10" aria-labelledby="groups-categories-heading" data-testid="groups-landing-categories">
        {cms.categories.kicker ? (
          <p className="text-jp-xs font-semibold uppercase tracking-wide text-jp-primary">{cms.categories.kicker}</p>
        ) : null}
        <h2 id="groups-categories-heading" className="mt-1 text-xl font-semibold text-jp-text">
          {cms.categories.title}
        </h2>
        {cms.categories.description ? (
          <p className="mt-1 max-w-2xl text-jp-sm text-jp-muted">{cms.categories.description}</p>
        ) : null}

        <GroupCategoryCards
          categories={facets.categories}
          mode="link"
          className="mt-5"
          disabled={facets.state !== "loaded"}
        />
      </section>
    </div>
  );
}
