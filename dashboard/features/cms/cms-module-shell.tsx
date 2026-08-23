import { DashboardLink as Link } from "@/components/dashboard/dashboard-link";
import { Breadcrumb, PageContainer, PageHeader } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { Skeleton } from "@/components/ui/skeleton";
import { ErrorState } from "@/components/ui/error-state";
import { CmsWorkspace } from "@/features/cms/cms-workspace";
import { HomepageSettingsPanel } from "@/features/cms/components/homepage-settings-panel";
import { PageSettingsPagesPanel } from "@/features/cms/components/page-settings-pages-panel";
import type { CmsModuleKey, CmsModuleResult } from "@/types/cms";

const SUBROUTES: { key: CmsModuleKey; label: string; href: string }[] = [
  { key: "overview", label: "Overview", href: "/cms" },
  { key: "pages", label: "Pages", href: "/cms/pages" },
  { key: "sections", label: "Homepage", href: "/cms/sections" },
  { key: "assets", label: "Media library", href: "/cms/assets" },
];

type Props = {
  module: CmsModuleKey;
  result: CmsModuleResult;
};

export function CmsModuleShell({ module, result }: Props) {
  const current = SUBROUTES.find((r) => r.key === module) ?? SUBROUTES[0];

  return (
    <PageContainer>
      <PreviewModeBadgeSlot />
      <PageHeader
        breadcrumb={
          <Breadcrumb items={[{ label: "Home" }, { label: "CMS" }, { label: current.label }]} />
        }
        title={current.label === "Overview" ? "CMS" : current.label}
        description={
          module === "pages"
            ? "Create, edit, preview, and publish JetPakistan pages. Homepage content is managed under Homepage."
            : module === "sections"
              ? "Authoritative homepage content. Preview the public home after save."
              : module === "assets"
                ? "Upload, list, and reuse media. Alt text is stored with each file."
                : "Pages, homepage, and media library are the operational CMS modules."
        }
      />
      <DataSourceNoticeSlot />

      <nav aria-label="CMS sections" className="flex flex-wrap gap-2">
        {SUBROUTES.map((route) => (
          <Link
            key={route.key}
            href={route.href}
            className="min-h-11 rounded-xl border border-jp-border bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent aria-[current=page]:border-jp-accent aria-[current=page]:bg-emerald-50"
            aria-current={route.key === module ? "page" : undefined}
          >
            {route.label}
          </Link>
        ))}
      </nav>

      {module === "sections" ? <HomepageSettingsPanel /> : null}
      {module === "pages" ? <PageSettingsPagesPanel /> : null}

      {result.state === "loading" ? (
        <div aria-busy="true" aria-label="Loading CMS foundation" data-testid="cms-loading-state">
          <Skeleton className="h-24 w-full" />
          <Skeleton className="mt-4 h-40 w-full" />
        </div>
      ) : (
        <CmsWorkspace result={result} />
      )}
    </PageContainer>
  );
}

export function CmsErrorShell({ referenceId, message }: { referenceId: string; message: string }) {
  return (
    <PageContainer>
      <PageHeader title="CMS" />
      <ErrorState title="Unable to load CMS" message={message} referenceId={referenceId} />
    </PageContainer>
  );
}
