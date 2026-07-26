"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Label } from "@/components/ui/page-layout";
import { Select } from "@/components/ui/select";
import { cmsQueryToSearchParams } from "@/lib/cms-query";
import type { CmsFacets, CmsModuleKey, CmsQuery } from "@/types/cms";

const MODULE_PATHS: Record<CmsModuleKey, string> = {
  overview: "",
  pages: "/pages",
  sections: "/sections",
  banners: "/banners",
  notices: "/notices",
  assets: "/assets",
};

type Props = {
  query: CmsQuery;
  facets: CmsFacets;
  module: CmsModuleKey;
};

export function CmsFilterBar({ query, facets, module }: Props) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [draft, setDraft] = useState(query);
  const modulePath = MODULE_PATHS[module];

  useEffect(() => {
    setDraft(query);
  }, [query]);

  const pushQuery = useCallback(
    (next: CmsQuery) => {
      const href = `/cms${modulePath}${cmsQueryToSearchParams(next)}`;
      startTransition(() => router.push(href));
    },
    [modulePath, router],
  );

  const apply = () => pushQuery({ ...draft, page: 1 });
  const reset = () => {
    const cleared: CmsQuery = {
      ...query,
      status: "all",
      pageType: "all",
      sectionType: "all",
      themeMode: "all",
      locale: "all",
      assetStatus: "all",
      bannerFamily: "all",
      noticeSeverity: "all",
      validationState: "all",
      audience: "all",
      placement: "",
      search: "",
      page: 1,
      sort: "lastUpdated",
      direction: "desc",
      selected: null,
      previewError: false,
      previewLoading: false,
      previewEmpty: false,
    };
    setDraft(cleared);
    pushQuery(cleared);
  };

  return (
    <Card className="space-y-4" data-testid="cms-filters">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-sm font-semibold text-gray-900">CMS filters</h2>
        <div className="flex flex-wrap gap-2">
          <Button variant="ghost" size="sm" type="button" onClick={reset}>
            Reset filters
          </Button>
          <Button size="sm" type="button" onClick={apply} disabled={pending} aria-busy={pending}>
            Apply filters
          </Button>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div>
          <Label htmlFor="cms-search">Search</Label>
          <input
            id="cms-search"
            type="search"
            className="mt-1 w-full min-h-11 rounded-xl border border-jp-border px-3 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
            value={draft.search}
            onChange={(e) => setDraft((d) => ({ ...d, search: e.target.value }))}
            onKeyDown={(e) => {
              if (e.key === "Enter") apply();
            }}
          />
        </div>

        {module !== "assets" ? (
          <div>
            <Label htmlFor="cms-status">Publication status</Label>
            <Select id="cms-status" value={draft.status} onChange={(e) => setDraft((d) => ({ ...d, status: e.target.value as CmsQuery["status"] }))}>
              <option value="all">All statuses</option>
              {facets.statuses.map((s) => (
                <option key={s} value={s}>
                  {s}
                </option>
              ))}
            </Select>
          </div>
        ) : null}

        {module === "pages" ? (
          <div>
            <Label htmlFor="cms-page-type">Page type</Label>
            <Select id="cms-page-type" value={draft.pageType} onChange={(e) => setDraft((d) => ({ ...d, pageType: e.target.value as CmsQuery["pageType"] }))}>
              <option value="all">All page types</option>
              {facets.pageTypes.map((t) => (
                <option key={t} value={t}>
                  {t.replace(/_/g, " ")}
                </option>
              ))}
            </Select>
          </div>
        ) : null}

        {module === "sections" ? (
          <>
            <div>
              <Label htmlFor="cms-section-type">Section type</Label>
              <Select id="cms-section-type" value={draft.sectionType} onChange={(e) => setDraft((d) => ({ ...d, sectionType: e.target.value as CmsQuery["sectionType"] }))}>
                <option value="all">All section types</option>
                {facets.sectionTypes.map((t) => (
                  <option key={t} value={t}>
                    {t}
                  </option>
                ))}
              </Select>
            </div>
            <div>
              <Label htmlFor="cms-theme-mode">Theme mode</Label>
              <Select id="cms-theme-mode" value={draft.themeMode} onChange={(e) => setDraft((d) => ({ ...d, themeMode: e.target.value as CmsQuery["themeMode"] }))}>
                <option value="all">All theme modes</option>
                {facets.themeModes.map((t) => (
                  <option key={t} value={t}>
                    {t}
                  </option>
                ))}
              </Select>
            </div>
          </>
        ) : null}

        {module === "banners" ? (
          <div>
            <Label htmlFor="cms-banner-family">Banner family</Label>
            <Select id="cms-banner-family" value={draft.bannerFamily} onChange={(e) => setDraft((d) => ({ ...d, bannerFamily: e.target.value as CmsQuery["bannerFamily"] }))}>
              <option value="all">All families</option>
              {facets.bannerFamilies.map((f) => (
                <option key={f} value={f}>
                  {f}
                </option>
              ))}
            </Select>
          </div>
        ) : null}

        {module === "notices" ? (
          <div>
            <Label htmlFor="cms-notice-severity">Severity</Label>
            <Select id="cms-notice-severity" value={draft.noticeSeverity} onChange={(e) => setDraft((d) => ({ ...d, noticeSeverity: e.target.value as CmsQuery["noticeSeverity"] }))}>
              <option value="all">All severities</option>
              {facets.noticeSeverities.map((s) => (
                <option key={s} value={s}>
                  {s.replace(/_/g, " ")}
                </option>
              ))}
            </Select>
          </div>
        ) : null}

        {module === "assets" ? (
          <div>
            <Label htmlFor="cms-asset-status">Approval status</Label>
            <Select id="cms-asset-status" value={draft.assetStatus} onChange={(e) => setDraft((d) => ({ ...d, assetStatus: e.target.value as CmsQuery["assetStatus"] }))}>
              <option value="all">All approval states</option>
              {facets.assetStatuses.map((s) => (
                <option key={s} value={s}>
                  {s}
                </option>
              ))}
            </Select>
          </div>
        ) : null}

        <div>
          <Label htmlFor="cms-validation-state">Validation state</Label>
          <Select id="cms-validation-state" value={draft.validationState} onChange={(e) => setDraft((d) => ({ ...d, validationState: e.target.value as CmsQuery["validationState"] }))}>
            <option value="all">All validation states</option>
            <option value="valid">Valid</option>
            <option value="warning">Warning</option>
            <option value="blocked">Blocked</option>
          </Select>
        </div>

        {(module === "pages" || module === "notices") && (
          <div>
            <Label htmlFor="cms-locale">Locale</Label>
            <Select id="cms-locale" value={draft.locale} onChange={(e) => setDraft((d) => ({ ...d, locale: e.target.value as CmsQuery["locale"] }))}>
              <option value="all">All locales</option>
              {facets.locales.map((l) => (
                <option key={l} value={l}>
                  {l}
                </option>
              ))}
            </Select>
          </div>
        )}
      </div>
    </Card>
  );
}
