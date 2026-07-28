import { CMS_BRAND } from "@/types/cms";
import type {
  CmsModuleKey,
  CmsModuleResult,
  CmsPage,
  CmsPageStatus,
  CmsPageType,
  CmsQuery,
  CmsTableColumn,
  CmsTableRow,
} from "@/types/cms";
import type { LaravelCmsPagesListPayload } from "@/lib/read-only/laravel/types";

const PAGE_COLUMNS: CmsTableColumn[] = [
  { key: "title", label: "Title", sortable: true },
  { key: "slug", label: "Slug", sortable: true },
  { key: "pageType", label: "Type", sortable: true },
  { key: "status", label: "Status", sortable: true },
  { key: "validationState", label: "Validation", sortable: true },
  { key: "updatedAt", label: "Updated", sortable: true, align: "end" },
];

export function mapCmsPage(row: Record<string, unknown>): CmsPage {
  return {
    id: String(row.id ?? ""),
    brand: CMS_BRAND.id,
    pageType: (row.pageType as CmsPageType) ?? "support",
    title: String(row.title ?? ""),
    slug: String(row.slug ?? ""),
    locale: "en-PK",
    status: (row.status as CmsPageStatus) ?? "draft",
    visibility: "preview_only",
    seoTitle: String((row.previewMetadata as { seoTitle?: string })?.seoTitle ?? row.title ?? ""),
    seoDescription: "",
    socialTitle: String(row.title ?? ""),
    socialDescription: "",
    canonicalPath: String((row.previewMetadata as { routeUrl?: string })?.routeUrl ?? `/pages/${row.slug}`),
    sectionIds: [],
    publicationWindow: { startDate: null, endDate: null },
    revisionNumber: 1,
    lastUpdatedDate: String(row.updatedAt ?? ""),
    updatedByUserId: "system",
    validation: {
      valid: row.validationState !== "blocked",
      issues: [],
    },
    previewAvailable: true,
  };
}

function toTableRow(page: CmsPage): CmsTableRow {
  return {
    id: page.id,
    title: page.title,
    slug: page.slug,
    pageType: page.pageType,
    status: page.status,
    validationState: page.validation.valid ? "valid" : "blocked",
    updatedAt: page.lastUpdatedDate,
    href: `/cms/pages?selected=${encodeURIComponent(page.id)}`,
  };
}

export function transformCmsModule(
  payload: LaravelCmsPagesListPayload,
  query: CmsQuery,
  module: CmsModuleKey,
  pagination: { page: number; pageSize: number; total: number; pageCount: number },
  selectedPage: CmsPage | null,
): CmsModuleResult {
  const pages = (payload.pages ?? []).map(mapCmsPage);
  const rows = pages.map(toTableRow);
  const published = pages.filter((p) => p.status === "published").length;
  const draft = pages.filter((p) => p.status === "draft").length;
  const facets = payload.facets as CmsModuleResult["facets"];

  return {
    state: pagination.total === 0 ? "empty" : "ready",
    module,
    query,
    brand: CMS_BRAND,
    metrics: [
      { key: "total_pages", label: "Total pages", value: pagination.total },
      { key: "published_pages", label: "Published pages", value: published },
      { key: "draft_pages", label: "Draft pages", value: draft },
    ],
    validationSummary: {
      valid: pages.filter((p) => p.validation.valid).length,
      warning: 0,
      blocked: pages.filter((p) => !p.validation.valid).length,
    },
    distributions: {
      publication: [
        { key: "published", label: "Published", value: published },
        { key: "draft", label: "Draft", value: draft },
      ],
      contentType: [],
      validation: [],
      theme: [],
      assets: [],
    },
    attentionQueue: [],
    recentRevisions: [],
    scheduledQueue: [],
    reviewQueue: [],
    table: {
      columns: PAGE_COLUMNS,
      rows,
      total: pagination.total,
      page: pagination.page,
      pageSize: pagination.pageSize,
      pageCount: pagination.pageCount,
    },
    selectedPage,
    selectedSection: null,
    selectedBanner: null,
    selectedNotice: null,
    selectedAsset: null,
    facets: {
      pageTypes: (facets?.pageTypes as string[]) ?? [],
      sectionTypes: [],
      statuses: (facets?.statuses as string[]) ?? [],
      themeModes: (facets?.themeModes as string[]) ?? [],
      locales: ["en-PK"],
      bannerFamilies: [],
      noticeSeverities: [],
      assetStatuses: [],
      placements: [],
      audiences: [],
    },
  };
}
