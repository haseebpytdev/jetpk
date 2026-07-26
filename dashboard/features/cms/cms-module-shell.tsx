import Link from "next/link";
import { Breadcrumb, PageContainer, PageHeader, PreviewDataBanner } from "@/components/ui/page-layout";
import { Skeleton } from "@/components/ui/skeleton";
import { ErrorState } from "@/components/ui/error-state";
import { CmsWorkspace } from "@/features/cms/cms-workspace";
import type { CmsModuleKey, CmsModuleResult } from "@/types/cms";

const SUBROUTES: { key: CmsModuleKey; label: string; href: string }[] = [
  { key: "overview", label: "Overview", href: "/cms" },
  { key: "pages", label: "Pages", href: "/cms/pages" },
  { key: "sections", label: "Sections", href: "/cms/sections" },
  { key: "banners", label: "Banners", href: "/cms/banners" },
  { key: "notices", label: "Notices", href: "/cms/notices" },
  { key: "assets", label: "Assets", href: "/cms/assets" },
];

type Props = {
  module: CmsModuleKey;
  result: CmsModuleResult;
};

export function CmsModuleShell({ module, result }: Props) {
  const current = SUBROUTES.find((r) => r.key === module) ?? SUBROUTES[0];

  return (
    <PageContainer>
      <PageHeader
        breadcrumb={
          <Breadcrumb items={[{ label: "Home" }, { label: "Inventory & pricing" }, { label: "CMS" }, { label: current.label }]} />
        }
        title="CMS"
        description="Theme-aware structured content for JetPakistan public pages. Preview data only — no live persistence."
      />
      <PreviewDataBanner />

      <div role="status" className="rounded-2xl border border-blue-200 bg-blue-50/60 px-4 py-3 text-sm text-blue-900">
        Dashboard preview only — content contracts are fixture-backed and not connected to the live frontend.
      </div>

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
