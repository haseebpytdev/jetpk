import { CmsModuleShell, CmsErrorShell } from "@/features/cms/cms-module-shell";
import { parseCmsQuery } from "@/lib/cms-query";
import { getDashboardMode } from "@/lib/preview";
import { CMS_BRAND, type CmsModuleKey, type CmsModuleResult, type CmsQuery } from "@/types/cms";
import { CmsServiceError, getCmsModule } from "@/services/cms-service";
import {
  ForbiddenState,
  SanitizedErrorState,
  ServiceUnavailableState,
  UnauthorizedState,
} from "@/components/ui/data-source-status";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { ReadOnlyServiceError } from "@/lib/read-only/read-only-service";

type Props = {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
  module: CmsModuleKey;
};

const LIVE_DOMAIN_NA_MODULES: CmsModuleKey[] = ["banners", "notices"];

function homepageOnlyResult(query: CmsQuery): CmsModuleResult {
  return {
    state: "ready",
    module: "sections",
    query,
    brand: CMS_BRAND,
    metrics: [],
    validationSummary: { valid: 0, warning: 0, blocked: 0 },
    distributions: { publication: [], contentType: [], validation: [], theme: [], assets: [] },
    attentionQueue: [],
    recentRevisions: [],
    scheduledQueue: [],
    reviewQueue: [],
    table: { columns: [], rows: [], total: 0, page: 1, pageSize: 25, pageCount: 1 },
    selectedPage: null,
    selectedSection: null,
    selectedBanner: null,
    selectedNotice: null,
    selectedAsset: null,
    facets: {
      pageTypes: [],
      sectionTypes: [],
      statuses: [],
      themeModes: [],
      locales: ["en-PK"],
      bannerFamilies: [],
      noticeSeverities: [],
      assetStatuses: [],
      placements: [],
      audiences: [],
    },
  };
}

export async function CmsPageContent({ searchParams, module }: Props) {
  const sp = await searchParams;
  const query = parseCmsQuery(sp);
  const isLive = getDashboardMode() === "live";

  if (isLive && LIVE_DOMAIN_NA_MODULES.includes(module)) {
    return <CmsLiveDomainNaShell module={module} />;
  }

  if (isLive && module === "sections") {
    return <CmsModuleShell module="sections" result={homepageOnlyResult(query)} />;
  }

  try {
    const result = await getCmsModule(query, module);
    return <CmsModuleShell module={module} result={result} />;
  } catch (e) {
    return (
      <PageContainer>
        <PageHeader title="CMS" />
        <CmsModuleError error={e} />
      </PageContainer>
    );
  }
}

function CmsLiveDomainNaShell({ module }: { module: CmsModuleKey }) {
  const label = module === "banners" ? "Banners" : "Notices";
  return (
    <PageContainer data-testid="cms-current-domain-na">
      <PageHeader title="CMS" description={`${label} management is not part of the current CMS domain.`} />
      <div className="rounded-xl border border-jp-border bg-white p-4 text-sm text-jp-muted">
        <p className="font-medium text-gray-900">CURRENT_DOMAIN_NA</p>
        <p className="mt-2">
          The current repository persists CMS pages, homepage sections, and media assets only. Independent {label.toLowerCase()}{" "}
          models are not present on this branch, so no operational {label.toLowerCase()} editor is exposed in live mode.
        </p>
        <p className="mt-2">Use CMS Pages, Homepage CMS, or Media Library for supported content management.</p>
      </div>
    </PageContainer>
  );
}

function CmsModuleError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="CMS" />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  if (error instanceof CmsServiceError) {
    return <CmsErrorShell referenceId={error.referenceId} message={error.message} />;
  }
  throw error;
}
