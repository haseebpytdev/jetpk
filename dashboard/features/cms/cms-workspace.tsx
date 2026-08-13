"use client";

import { useCallback, useEffect, useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { useDashboardRouter } from "@/lib/dashboard-navigation";
import { Drawer } from "@/components/ui/drawer";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination } from "@/components/ui/pagination";
import { Card, CardDescription, CardTitle } from "@/components/ui/card";
import { CmsStatusBadge } from "@/components/ui/status-badge";
import { CmsActiveFilters } from "@/features/cms/components/cms-active-filters";
import { CmsAttentionQueue } from "@/features/cms/components/cms-attention-queue";
import { CmsDataTable } from "@/features/cms/components/cms-data-table";
import { CmsFilterBar } from "@/features/cms/components/cms-filter-bar";
import { CmsRevisionTimeline } from "@/features/cms/components/cms-revision-timeline";
import { CmsSummaryMetrics } from "@/features/cms/components/cms-summary-metrics";
import { PageDetailDrawerContent } from "@/features/cms/components/page-detail-drawer";
import { SectionDetailDrawerContent } from "@/features/cms/components/section-detail-drawer";
import { BannerDetailDrawerContent } from "@/features/cms/components/banner-detail-drawer";
import { NoticeDetailDrawerContent } from "@/features/cms/components/notice-detail-drawer";
import { AssetDetailDrawerContent } from "@/features/cms/components/asset-detail-drawer";
import { cmsQueryToSearchParams } from "@/lib/cms-query";
import type { CmsModuleResult, CmsPreviewMode } from "@/types/cms";

const MODULE_PATHS: Record<CmsModuleResult["module"], string> = {
  overview: "",
  pages: "/pages",
  sections: "/sections",
  banners: "/banners",
  notices: "/notices",
  assets: "/assets",
};

type Props = {
  result: CmsModuleResult;
};

export function CmsWorkspace({ result }: Props) {
  const isLive = useDashboardLiveMode();
  const router = useDashboardRouter();
  const [drawerDismissed, setDrawerDismissed] = useState(false);
  const previewMode = result.query.previewMode;
  const modulePath = MODULE_PATHS[result.module];

  useEffect(() => {
    setDrawerDismissed(false);
  }, [result.query.selected]);

  const pushQuery = useCallback(
    (overrides: Partial<CmsModuleResult["query"]>) => {
      const next = { ...result.query, ...overrides };
      router.push(`/cms${modulePath}${cmsQueryToSearchParams(next)}`);
    },
    [modulePath, result.query, router],
  );

  const onSort = (key: string) => {
    const direction = result.query.sort === key && result.query.direction === "desc" ? "asc" : "desc";
    pushQuery({ sort: key, direction, page: 1 });
  };

  const onView = (id: string) => {
    pushQuery({ selected: id });
  };

  const onCloseDrawer = useCallback(() => {
    setDrawerDismissed(true);
    const next = { ...result.query, selected: null };
    router.replace(`/cms${modulePath}${cmsQueryToSearchParams(next)}`);
  }, [modulePath, result.query, router]);

  const onPreviewModeChange = (mode: CmsPreviewMode) => {
    pushQuery({ previewMode: mode });
  };

  const hasSelection = Boolean(result.query.selected);
  const drawerOpen = !drawerDismissed && hasSelection;
  const empty = result.module !== "overview" && result.table.total === 0;

  let drawerTitle = "CMS record";
  let drawerDescription: string | undefined;
  let drawerContent: React.ReactNode = null;
  let closeLabel = "Close CMS details";

  if (result.selectedPage) {
    drawerTitle = result.selectedPage.title;
    drawerDescription = `${result.selectedPage.id} · ${result.selectedPage.pageType}`;
    closeLabel = "Close page details";
    drawerContent = <PageDetailDrawerContent page={result.selectedPage} previewMode={previewMode} onPreviewModeChange={onPreviewModeChange} />;
  } else if (result.selectedSection) {
    drawerTitle = result.selectedSection.sectionType;
    drawerDescription = result.selectedSection.id;
    closeLabel = "Close section details";
    drawerContent = <SectionDetailDrawerContent section={result.selectedSection} previewMode={previewMode} onPreviewModeChange={onPreviewModeChange} />;
  } else if (result.selectedBanner) {
    drawerTitle = result.selectedBanner.title;
    drawerDescription = `${result.selectedBanner.id} · ${result.selectedBanner.family}`;
    closeLabel = "Close banner details";
    drawerContent = <BannerDetailDrawerContent banner={result.selectedBanner} previewMode={previewMode} onPreviewModeChange={onPreviewModeChange} />;
  } else if (result.selectedNotice) {
    drawerTitle = result.selectedNotice.title;
    drawerDescription = result.selectedNotice.id;
    closeLabel = "Close notice details";
    drawerContent = <NoticeDetailDrawerContent notice={result.selectedNotice} />;
  } else if (result.selectedAsset) {
    drawerTitle = result.selectedAsset.internalName;
    drawerDescription = result.selectedAsset.id;
    closeLabel = "Close asset details";
    drawerContent = <AssetDetailDrawerContent asset={result.selectedAsset} previewMode={previewMode} onPreviewModeChange={onPreviewModeChange} />;
  }

  return (
    <div className="space-y-4" data-testid="cms-workspace">
      {result.module !== "overview" ? (
        <>
          <CmsFilterBar query={result.query} facets={result.facets} module={result.module} />
          <CmsActiveFilters query={result.query} />
        </>
      ) : null}

      <p className="text-sm text-jp-muted">
        {isLive ? (
          result.module === "pages" ? (
            <>
              Live <strong>Pages</strong> for brand <strong>{result.brand.label}</strong>. Create/edit/archive uses the
              Laravel CMS pages JSON path. Banners, notices, and assets stay read-only until a write domain exists (no
              Wave-2 migration).
            </>
          ) : result.module === "overview" ? (
            <>
              CMS overview for brand <strong>{result.brand.label}</strong>. Pages are operational; banners, notices, and
              assets remain read-only listings.
            </>
          ) : (
            <>
              Read-only <strong>{result.module}</strong> listing for brand <strong>{result.brand.label}</strong>. No
              Laravel mutation domain in Wave 2 — view/filter only.
            </>
          )
        ) : (
          <>
            CMS content is structured, fixture-backed preview data. Brand: <strong>{result.brand.label}</strong> (fixed).
          </>
        )}
      </p>

      {result.state === "empty" ? (
        <EmptyState
          title="No CMS records match filters"
          description={
            isLive
              ? "Adjust filters or reset to view CMS records."
              : "Adjust filters or reset to view preview content. This does not imply the live site has no content."
          }
        />
      ) : null}

      {result.state === "ready" && result.module === "overview" ? (
        <>
          <section aria-labelledby="cms-overview-metrics">
            <h2 id="cms-overview-metrics" className="text-sm font-semibold text-gray-900">Content readiness</h2>
            <div className="mt-3">
              <CmsSummaryMetrics metrics={result.metrics} />
            </div>
          </section>

          <div className="grid gap-4 lg:grid-cols-2">
            <DistributionCard title="Publication status" segments={result.distributions.publication} testId="cms-distribution-publication" />
            <DistributionCard title="Content types" segments={result.distributions.contentType} testId="cms-distribution-content-type" />
            <DistributionCard title="Validation health" segments={result.distributions.validation} testId="cms-distribution-validation" />
            <DistributionCard title="Theme compatibility" segments={result.distributions.theme} testId="cms-distribution-theme" />
            <DistributionCard title="Asset approval" segments={result.distributions.assets} testId="cms-distribution-assets" />
          </div>

          <CmsAttentionQueue items={result.attentionQueue} />

          <div className="grid gap-4 lg:grid-cols-2">
            <CmsRevisionTimeline revisions={result.recentRevisions} />
            <Card data-testid="cms-scheduled-queue">
              <CardTitle>Scheduled publication queue</CardTitle>
              <CardDescription className="mt-1">
                Scheduling metadata only. Publish or archive from the Pages editor.
              </CardDescription>
              {result.scheduledQueue.length === 0 ? (
                <p className="mt-3 text-sm text-jp-muted">No scheduled items.</p>
              ) : (
                <ul className="mt-3 space-y-2 text-sm">
                  {result.scheduledQueue.map((item) => (
                    <li key={item.id} className="rounded-lg border border-jp-border px-3 py-2">
                      <span className="font-medium">{item.title}</span>
                      <span className="text-jp-muted"> · {item.startDate}</span>
                    </li>
                  ))}
                </ul>
              )}
            </Card>
          </div>
        </>
      ) : null}

      {result.state === "ready" && result.module !== "overview" ? (
        empty ? null : (
          <>
            <CmsDataTable
              table={result.table}
              onSort={onSort}
              sort={result.query.sort}
              direction={result.query.direction}
              onView={onView}
              mobileTitle={`${result.module} record`}
            />
            <Pagination
              page={result.table.page}
              pageCount={result.table.pageCount}
              pageSize={result.table.pageSize}
              total={result.table.total}
              onPageChange={(page) => pushQuery({ page })}
              onPageSizeChange={(pageSize) => pushQuery({ pageSize, page: 1 })}
              ariaLabel="CMS pagination"
            />
          </>
        )
      ) : null}

      <Drawer open={drawerOpen} onClose={onCloseDrawer} title={drawerTitle} description={drawerDescription} closeAriaLabel={closeLabel} size={result.selectedPage ? "wide" : "default"}>
        {drawerContent}
      </Drawer>
    </div>
  );
}

function DistributionCard({
  title,
  segments,
  testId,
}: {
  title: string;
  segments: { key: string; label: string; value: number }[];
  testId: string;
}) {
  return (
    <Card className="p-4" data-testid={testId}>
      <CardTitle className="text-base">{title}</CardTitle>
      <ul className="mt-3 space-y-2">
        {segments.map((seg) => (
          <li key={seg.key} className="flex items-center justify-between text-sm">
            <span className="capitalize">{seg.label}</span>
            <CmsStatusBadge status={seg.key} label={String(seg.value)} />
          </li>
        ))}
      </ul>
    </Card>
  );
}
