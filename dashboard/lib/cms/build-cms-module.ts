import { buildCmsAttentionQueue } from "@/lib/cms/attention-queue";
import {
  buildCmsFacets,
  filterAssets,
  filterBanners,
  filterNotices,
  filterPages,
  filterSections,
  paginate,
  resolveValidation,
  sortRows,
} from "@/lib/cms/query-filters";
import { getSectionDefinition } from "@/features/cms/registry/section-registry";
import {
  mockCmsAssets,
  mockCmsBanners,
  mockCmsNotices,
  mockCmsPages,
  mockCmsRevisions,
  mockCmsSections,
} from "@/mocks/cms-fixtures";
import { CMS_BRAND } from "@/types/cms";
import type {
  CmsDistributionSegment,
  CmsMetric,
  CmsModuleKey,
  CmsModuleResult,
  CmsModuleTable,
  CmsQuery,
  CmsTableColumn,
  CmsTableRow,
} from "@/types/cms";

function countValidationSummary() {
  const all = [...mockCmsPages, ...mockCmsSections, ...mockCmsBanners, ...mockCmsNotices];
  let valid = 0;
  let warning = 0;
  let blocked = 0;
  for (const record of all) {
    const issues = "validation" in record ? record.validation.issues : [];
    if (issues.some((i) => i.blocking)) blocked += 1;
    else if (issues.some((i) => i.severity === "warning")) warning += 1;
    else valid += 1;
  }
  for (const asset of mockCmsAssets) {
    const result = resolveValidation(asset, "asset");
    if (!result.valid) blocked += 1;
    else if (result.issues.length > 0) warning += 1;
    else valid += 1;
  }
  return { valid, warning, blocked };
}

function overviewMetrics(): CmsMetric[] {
  const publishedPages = mockCmsPages.filter((p) => p.status === "published").length;
  const draftPages = mockCmsPages.filter((p) => p.status === "draft").length;
  const scheduled = [...mockCmsPages, ...mockCmsBanners, ...mockCmsNotices].filter((r) => r.status === "scheduled").length;
  const sectionsReview = mockCmsSections.filter((s) => {
    const v = resolveValidation(s, "section");
    return v.issues.length > 0;
  }).length;
  const activeBanners = mockCmsBanners.filter((b) => b.status === "published").length;
  const activeNotices = mockCmsNotices.filter((n) => n.status === "published").length;
  const unapprovedAssets = mockCmsAssets.filter((a) => a.approvalStatus !== "approved").length;
  const summary = countValidationSummary();
  const themeCoverage = mockCmsSections.filter((s) => s.themeMode === "automatic" || s.themeMode === "dualAsset").length;

  return [
    { key: "total_pages", label: "Total pages", value: mockCmsPages.length },
    { key: "published_pages", label: "Published pages", value: publishedPages },
    { key: "draft_pages", label: "Draft pages", value: draftPages },
    { key: "scheduled", label: "Scheduled content", value: scheduled },
    { key: "sections_review", label: "Sections requiring review", value: sectionsReview },
    { key: "active_banners", label: "Active banners", value: activeBanners },
    { key: "active_notices", label: "Active notices", value: activeNotices },
    { key: "unapproved_assets", label: "Unapproved assets", value: unapprovedAssets },
    { key: "validation_warnings", label: "Validation warnings", value: summary.warning },
    { key: "blocking_issues", label: "Blocking validation issues", value: summary.blocked },
    { key: "recent_revisions", label: "Recently revised items", value: mockCmsRevisions.length },
    { key: "theme_coverage", label: "Day/night compatibility coverage", value: themeCoverage },
  ];
}

function distributionPublication(): CmsDistributionSegment[] {
  const counts = new Map<string, number>();
  for (const page of mockCmsPages) {
    counts.set(page.status, (counts.get(page.status) ?? 0) + 1);
  }
  return [...counts.entries()].map(([key, value]) => ({ key, label: key, value }));
}

function distributionContentType(): CmsDistributionSegment[] {
  const counts = new Map<string, number>();
  for (const page of mockCmsPages) {
    counts.set(page.pageType, (counts.get(page.pageType) ?? 0) + 1);
  }
  return [...counts.entries()].map(([key, value]) => ({ key, label: key.replace(/_/g, " "), value }));
}

function distributionValidation(): CmsDistributionSegment[] {
  const summary = countValidationSummary();
  return [
    { key: "valid", label: "Valid", value: summary.valid },
    { key: "warning", label: "Warnings", value: summary.warning },
    { key: "blocked", label: "Blocked", value: summary.blocked },
  ];
}

function distributionTheme(): CmsDistributionSegment[] {
  const counts = new Map<string, number>();
  for (const section of mockCmsSections) {
    counts.set(section.themeMode, (counts.get(section.themeMode) ?? 0) + 1);
  }
  return [...counts.entries()].map(([key, value]) => ({ key, label: key, value }));
}

function distributionAssets(): CmsDistributionSegment[] {
  const counts = new Map<string, number>();
  for (const asset of mockCmsAssets) {
    counts.set(asset.approvalStatus, (counts.get(asset.approvalStatus) ?? 0) + 1);
  }
  return [...counts.entries()].map(([key, value]) => ({ key, label: key, value }));
}

function validationLabel(result: ReturnType<typeof resolveValidation>): string {
  if (result.issues.some((i) => i.blocking)) return "Blocked";
  if (result.issues.some((i) => i.severity === "warning")) return "Warning";
  return "Valid";
}

function buildPageRows(query: CmsQuery): CmsTableRow[] {
  const filtered = filterPages(query);
  const rows: CmsTableRow[] = filtered.map((page) => {
    const validation = resolveValidation(page, "page");
    return {
      id: page.id,
      title: page.title,
      pageType: page.pageType.replace(/_/g, " "),
      slug: page.slug,
      locale: page.locale,
      status: page.status,
      visibility: page.visibility,
      sectionCount: page.sectionIds.length,
      validation: validationLabel(validation),
      revision: page.revisionNumber,
      lastUpdated: page.lastUpdatedDate.slice(0, 10),
      updatedBy: page.updatedByUserId,
      preview: page.previewAvailable ? "Available" : "Unavailable",
      href: `/cms/pages?selected=${encodeURIComponent(page.id)}`,
    };
  });
  return sortRows(rows, query.sort === "lastUpdated" ? "lastUpdated" : query.sort, query.direction);
}

function buildSectionRows(query: CmsQuery): CmsTableRow[] {
  const filtered = filterSections(query);
  const rows: CmsTableRow[] = filtered.map((section) => {
    const def = getSectionDefinition(section.sectionType);
    const page = mockCmsPages.find((p) => p.id === section.pageId);
    const validation = resolveValidation(section, "section");
    return {
      id: section.id,
      label: def?.label ?? section.sectionType,
      sectionType: section.sectionType,
      componentKey: def?.frontendComponentKey ?? section.sectionType,
      assignedPage: page?.title ?? section.pageId,
      order: section.sortOrder,
      variant: section.variant,
      themeMode: section.themeMode,
      deviceVisibility: section.deviceVisibility,
      status: page?.status ?? "draft",
      validation: validationLabel(validation),
      assetCount: section.assetIds.length,
      revision: page?.revisionNumber ?? 1,
      lastUpdated: page?.lastUpdatedDate.slice(0, 10) ?? "2026-06-15",
      href: `/cms/sections?selected=${encodeURIComponent(section.id)}`,
    };
  });
  return sortRows(rows, query.sort === "lastUpdated" ? "order" : query.sort, query.direction);
}

function buildBannerRows(query: CmsQuery): CmsTableRow[] {
  const filtered = filterBanners(query);
  const rows: CmsTableRow[] = filtered.map((banner) => {
    const validation = resolveValidation(banner, "banner");
    const asset = mockCmsAssets.find((a) => a.id === banner.desktopAssetId);
    return {
      id: banner.id,
      internalName: banner.title,
      family: banner.family,
      placement: banner.placements.join(", "),
      desktopRatio: banner.desktopAspectRatio,
      mobileRatio: banner.mobileAspectRatio,
      themeMode: banner.supportsDayNight ? "dualAsset" : "day",
      status: banner.status,
      startDate: banner.publicationWindow.startDate ?? "—",
      endDate: banner.publicationWindow.endDate ?? "—",
      priority: banner.priority,
      locale: "en-PK",
      audience: "all",
      approval: asset?.approvalStatus ?? "unapproved",
      validation: validationLabel(validation),
      revision: 1,
      lastUpdated: "2026-06-15",
      href: `/cms/banners?selected=${encodeURIComponent(banner.id)}`,
    };
  });
  return sortRows(rows, query.sort === "lastUpdated" ? "priority" : query.sort, query.direction);
}

function buildNoticeRows(query: CmsQuery): CmsTableRow[] {
  const filtered = filterNotices(query);
  const rows: CmsTableRow[] = filtered.map((notice) => ({
    id: notice.id,
    title: notice.title,
    messageSummary: notice.message.slice(0, 60) + (notice.message.length > 60 ? "…" : ""),
    severity: notice.severity,
    placement: notice.placement,
    audience: notice.audience,
    startDate: notice.startDate,
    endDate: notice.endDate ?? "—",
    dismissible: notice.dismissible ? "Yes" : "No",
    cta: notice.cta?.label ?? "—",
    themeTreatment: notice.themeTreatment,
    priority: notice.priority,
    locale: notice.locale,
    status: notice.status,
    validation: validationLabel(notice.validation),
    revision: 1,
    href: `/cms/notices?selected=${encodeURIComponent(notice.id)}`,
  }));
  return sortRows(rows, query.sort === "lastUpdated" ? "priority" : query.sort, query.direction);
}

function buildAssetRows(query: CmsQuery): CmsTableRow[] {
  const filtered = filterAssets(query);
  const rows: CmsTableRow[] = filtered.map((asset) => {
    const validation = resolveValidation(asset, "asset");
    return {
      id: asset.id,
      internalName: asset.internalName,
      category: asset.category,
      fileType: asset.fileType,
      width: asset.desktop.width,
      height: asset.desktop.height,
      aspectRatio: asset.desktop.aspectRatio,
      desktop: "Yes",
      mobile: asset.mobile.width > 0 ? "Yes" : "No",
      day: asset.dayVariant ? "Yes" : "No",
      night: asset.nightVariant ? "Yes" : "No",
      altText: asset.altText.trim() ? "Present" : "Missing",
      focalPoint: `${asset.focalPointX}, ${asset.focalPointY}`,
      safeArea: asset.safeArea,
      approval: asset.approvalStatus,
      usageCount: asset.usageCount,
      validation: validationLabel(validation),
      createdDate: asset.createdDate,
      updatedDate: asset.updatedDate,
      author: asset.authorId,
      href: `/cms/assets?selected=${encodeURIComponent(asset.id)}`,
    };
  });
  return sortRows(rows, query.sort === "lastUpdated" ? "updatedDate" : query.sort, query.direction);
}

const PAGE_COLUMNS: CmsTableColumn[] = [
  { key: "id", label: "Page ID", sortable: true },
  { key: "title", label: "Title", sortable: true },
  { key: "pageType", label: "Page type" },
  { key: "slug", label: "Slug" },
  { key: "locale", label: "Locale" },
  { key: "status", label: "Status" },
  { key: "visibility", label: "Visibility" },
  { key: "sectionCount", label: "Sections", align: "end" },
  { key: "validation", label: "Validation" },
  { key: "revision", label: "Revision", align: "end" },
  { key: "lastUpdated", label: "Last updated", sortable: true },
  { key: "updatedBy", label: "Updated by" },
  { key: "preview", label: "Preview" },
];

const SECTION_COLUMNS: CmsTableColumn[] = [
  { key: "id", label: "Section ID", sortable: true },
  { key: "label", label: "Label", sortable: true },
  { key: "sectionType", label: "Section type" },
  { key: "componentKey", label: "Component key" },
  { key: "assignedPage", label: "Assigned page" },
  { key: "order", label: "Order", align: "end", sortable: true },
  { key: "variant", label: "Variant" },
  { key: "themeMode", label: "Theme mode" },
  { key: "deviceVisibility", label: "Device" },
  { key: "status", label: "Status" },
  { key: "validation", label: "Validation" },
  { key: "assetCount", label: "Assets", align: "end" },
  { key: "revision", label: "Revision", align: "end" },
  { key: "lastUpdated", label: "Last updated", sortable: true },
];

const BANNER_COLUMNS: CmsTableColumn[] = [
  { key: "id", label: "Banner ID", sortable: true },
  { key: "internalName", label: "Internal name", sortable: true },
  { key: "family", label: "Family" },
  { key: "placement", label: "Placement" },
  { key: "desktopRatio", label: "Desktop ratio" },
  { key: "mobileRatio", label: "Mobile ratio" },
  { key: "themeMode", label: "Theme" },
  { key: "status", label: "Status" },
  { key: "startDate", label: "Start" },
  { key: "endDate", label: "End" },
  { key: "priority", label: "Priority", align: "end", sortable: true },
  { key: "approval", label: "Asset approval" },
  { key: "validation", label: "Validation" },
  { key: "lastUpdated", label: "Last updated" },
];

const NOTICE_COLUMNS: CmsTableColumn[] = [
  { key: "id", label: "Notice ID", sortable: true },
  { key: "title", label: "Title", sortable: true },
  { key: "messageSummary", label: "Message" },
  { key: "severity", label: "Severity" },
  { key: "placement", label: "Placement" },
  { key: "audience", label: "Audience" },
  { key: "startDate", label: "Start" },
  { key: "endDate", label: "End" },
  { key: "dismissible", label: "Dismissible" },
  { key: "status", label: "Status" },
  { key: "validation", label: "Validation" },
  { key: "priority", label: "Priority", align: "end", sortable: true },
];

const ASSET_COLUMNS: CmsTableColumn[] = [
  { key: "id", label: "Asset ID", sortable: true },
  { key: "internalName", label: "Internal name", sortable: true },
  { key: "category", label: "Category" },
  { key: "fileType", label: "File type" },
  { key: "width", label: "Width", align: "end" },
  { key: "height", label: "Height", align: "end" },
  { key: "aspectRatio", label: "Aspect ratio" },
  { key: "desktop", label: "Desktop" },
  { key: "mobile", label: "Mobile" },
  { key: "day", label: "Day" },
  { key: "night", label: "Night" },
  { key: "altText", label: "Alt text" },
  { key: "approval", label: "Approval" },
  { key: "usageCount", label: "Usage", align: "end" },
  { key: "validation", label: "Validation" },
  { key: "updatedDate", label: "Updated", sortable: true },
];

function buildTable(module: CmsModuleKey, query: CmsQuery): CmsModuleTable {
  let columns: CmsTableColumn[] = [];
  let rows: CmsTableRow[] = [];

  switch (module) {
    case "pages":
      columns = PAGE_COLUMNS;
      rows = buildPageRows(query);
      break;
    case "sections":
      columns = SECTION_COLUMNS;
      rows = buildSectionRows(query);
      break;
    case "banners":
      columns = BANNER_COLUMNS;
      rows = buildBannerRows(query);
      break;
    case "notices":
      columns = NOTICE_COLUMNS;
      rows = buildNoticeRows(query);
      break;
    case "assets":
      columns = ASSET_COLUMNS;
      rows = buildAssetRows(query);
      break;
    default:
      columns = [];
      rows = [];
  }

  const paged = paginate(rows, query.page, query.pageSize);
  return {
    columns,
    rows: paged.items,
    total: paged.total,
    page: paged.page,
    pageSize: query.pageSize,
    pageCount: paged.pageCount,
  };
}

function emptyTable(query: CmsQuery): CmsModuleTable {
  return { columns: [], rows: [], total: 0, page: 1, pageSize: query.pageSize, pageCount: 1 };
}

export function buildCmsModule(module: CmsModuleKey, query: CmsQuery): CmsModuleResult {
  const validationSummary = countValidationSummary();
  const attentionQueue = buildCmsAttentionQueue();
  const recentRevisions = [...mockCmsRevisions].sort((a, b) => b.timestamp.localeCompare(a.timestamp)).slice(0, 8);
  const scheduledQueue = [
    ...mockCmsPages.filter((p) => p.status === "scheduled").map((p) => ({
      id: p.id,
      title: p.title,
      type: "page",
      startDate: p.publicationWindow.startDate ?? "2026-06-01",
      href: `/cms/pages?selected=${encodeURIComponent(p.id)}`,
    })),
    ...mockCmsBanners.filter((b) => b.status === "scheduled").map((b) => ({
      id: b.id,
      title: b.title,
      type: "banner",
      startDate: b.publicationWindow.startDate ?? "2026-06-01",
      href: `/cms/banners?selected=${encodeURIComponent(b.id)}`,
    })),
    ...mockCmsNotices.filter((n) => n.status === "scheduled").map((n) => ({
      id: n.id,
      title: n.title,
      type: "notice",
      startDate: n.startDate,
      href: `/cms/notices?selected=${encodeURIComponent(n.id)}`,
    })),
  ];

  const reviewQueue = mockCmsSections
    .filter((s) => resolveValidation(s, "section").issues.length > 0)
    .slice(0, 6)
    .map((s) => {
      const def = getSectionDefinition(s.sectionType);
      return {
        id: s.id,
        title: def?.label ?? s.sectionType,
        reason: "Validation issues require review",
        href: `/cms/sections?selected=${encodeURIComponent(s.id)}`,
      };
    });

  const selectedPage = query.selected && module === "pages" ? (mockCmsPages.find((p) => p.id === query.selected) ?? null) : null;
  const selectedSection = query.selected && module === "sections" ? (mockCmsSections.find((s) => s.id === query.selected) ?? null) : null;
  const selectedBanner = query.selected && module === "banners" ? (mockCmsBanners.find((b) => b.id === query.selected) ?? null) : null;
  const selectedNotice = query.selected && module === "notices" ? (mockCmsNotices.find((n) => n.id === query.selected) ?? null) : null;
  const selectedAsset = query.selected && module === "assets" ? (mockCmsAssets.find((a) => a.id === query.selected) ?? null) : null;

  return {
    state: "ready",
    module,
    query,
    brand: CMS_BRAND,
    metrics: module === "overview" ? overviewMetrics() : [],
    validationSummary,
    distributions: {
      publication: distributionPublication(),
      contentType: distributionContentType(),
      validation: distributionValidation(),
      theme: distributionTheme(),
      assets: distributionAssets(),
    },
    attentionQueue,
    recentRevisions,
    scheduledQueue,
    reviewQueue,
    table: module === "overview" ? emptyTable(query) : buildTable(module, query),
    selectedPage,
    selectedSection,
    selectedBanner,
    selectedNotice,
    selectedAsset,
    facets: buildCmsFacets(),
  };
}
