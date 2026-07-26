import type {
  CmsAssetApprovalStatus,
  CmsBannerFamily,
  CmsLocale,
  CmsNoticeSeverity,
  CmsPageStatus,
  CmsPageType,
  CmsPreviewMode,
  CmsQuery,
  CmsSectionType,
  CmsThemeMode,
} from "@/types/cms";

function first(value: string | string[] | undefined): string {
  if (Array.isArray(value)) return value[0] ?? "";
  return value ?? "";
}

const PAGE_STATUSES: CmsPageStatus[] = [
  "draft",
  "inReview",
  "approved",
  "scheduled",
  "published",
  "expired",
  "archived",
];

const PAGE_TYPES: CmsPageType[] = [
  "homepage",
  "about",
  "contact",
  "faq",
  "privacy",
  "terms",
  "refund",
  "support",
  "travel_guidance",
  "umrah",
  "airline_info",
  "destination_landing",
  "campaign_landing",
];

const THEME_MODES: CmsThemeMode[] = ["automatic", "day", "night", "dualAsset", "neutral"];
const LOCALES: CmsLocale[] = ["en-PK", "ur-PK"];
const ASSET_STATUSES: CmsAssetApprovalStatus[] = ["approved", "pending", "rejected", "unapproved"];
const BANNER_FAMILIES: CmsBannerFamily[] = [
  "hero",
  "support",
  "offer",
  "destination",
  "airline",
  "campaign",
  "promotion",
  "notice",
];
const NOTICE_SEVERITIES: CmsNoticeSeverity[] = [
  "information",
  "success",
  "warning",
  "urgent",
  "maintenance",
  "airline_advisory",
  "visa_update",
  "service_interruption",
  "promotion",
];

const PREVIEW_MODES: CmsPreviewMode[] = [
  "desktop_day",
  "desktop_night",
  "tablet",
  "mobile_day",
  "mobile_night",
];

const SECTION_TYPES: CmsSectionType[] = [
  "homepage.hero",
  "homepage.flightSearchContext",
  "homepage.featuredOffers",
  "homepage.popularRoutes",
  "homepage.featuredDestinations",
  "homepage.airlineHighlights",
  "homepage.trustBenefits",
  "homepage.supportCallout",
  "homepage.faqPreview",
  "homepage.newsletterCallout",
  "global.noticeStrip",
  "global.promotionBanner",
  "content.richText",
  "content.policyPage",
  "content.contactBlock",
  "content.faqCollection",
  "content.destinationHero",
  "content.destinationHighlights",
];

function parseEnum<T extends string>(raw: string, allowed: readonly T[], fallback: T | "all"): T | "all" {
  if (!raw || raw === "all") return fallback;
  return (allowed as readonly string[]).includes(raw) ? (raw as T) : fallback;
}

function parsePositiveInt(raw: string, fallback: number): number {
  const n = Number.parseInt(raw, 10);
  return Number.isFinite(n) && n >= 1 ? n : fallback;
}

export function parseCmsQuery(searchParams: Record<string, string | string[] | undefined>): CmsQuery {
  const selected = first(searchParams.selected).trim();
  return {
    status: parseEnum(first(searchParams.status), PAGE_STATUSES, "all"),
    pageType: parseEnum(first(searchParams.pageType), PAGE_TYPES, "all"),
    sectionType: parseEnum(first(searchParams.sectionType), SECTION_TYPES, "all"),
    themeMode: parseEnum(first(searchParams.themeMode), THEME_MODES, "all"),
    locale: parseEnum(first(searchParams.locale), LOCALES, "all"),
    assetStatus: parseEnum(first(searchParams.assetStatus), ASSET_STATUSES, "all"),
    bannerFamily: parseEnum(first(searchParams.bannerFamily), BANNER_FAMILIES, "all"),
    noticeSeverity: parseEnum(first(searchParams.noticeSeverity), NOTICE_SEVERITIES, "all"),
    validationState: parseEnum(first(searchParams.validationState), ["valid", "warning", "blocked"] as const, "all"),
    audience: parseEnum(first(searchParams.audience), ["all", "guest", "agent"] as const, "all"),
    placement: first(searchParams.placement).trim(),
    search: first(searchParams.search).trim(),
    page: parsePositiveInt(first(searchParams.page), 1),
    pageSize: [10, 20, 50].includes(parsePositiveInt(first(searchParams.pageSize), 20))
      ? parsePositiveInt(first(searchParams.pageSize), 20)
      : 20,
    sort: first(searchParams.sort) || "lastUpdated",
    direction: first(searchParams.direction) === "asc" ? "asc" : "desc",
    selected: selected || null,
    previewMode: (PREVIEW_MODES as readonly string[]).includes(first(searchParams.previewMode))
      ? (first(searchParams.previewMode) as CmsPreviewMode)
      : "desktop_day",
    previewError: first(searchParams.previewError) === "1",
    previewLoading: first(searchParams.previewLoading) === "1",
    previewEmpty: first(searchParams.previewEmpty) === "1",
  };
}

export function cmsQueryToSearchParams(query: CmsQuery, overrides?: Partial<CmsQuery>): string {
  const merged = { ...query, ...overrides };
  const params = new URLSearchParams();

  if (merged.status !== "all") params.set("status", merged.status);
  if (merged.pageType !== "all") params.set("pageType", merged.pageType);
  if (merged.sectionType !== "all") params.set("sectionType", merged.sectionType);
  if (merged.themeMode !== "all") params.set("themeMode", merged.themeMode);
  if (merged.locale !== "all") params.set("locale", merged.locale);
  if (merged.assetStatus !== "all") params.set("assetStatus", merged.assetStatus);
  if (merged.bannerFamily !== "all") params.set("bannerFamily", merged.bannerFamily);
  if (merged.noticeSeverity !== "all") params.set("noticeSeverity", merged.noticeSeverity);
  if (merged.validationState !== "all") params.set("validationState", merged.validationState);
  if (merged.search) params.set("search", merged.search);
  if (merged.page > 1) params.set("page", String(merged.page));
  if (merged.pageSize !== 20) params.set("pageSize", String(merged.pageSize));
  if (merged.sort !== "lastUpdated") params.set("sort", merged.sort);
  if (merged.direction !== "desc") params.set("direction", merged.direction);
  if (merged.audience !== "all") params.set("audience", merged.audience);
  if (merged.placement) params.set("placement", merged.placement);
  if (merged.selected) params.set("selected", merged.selected);
  if (merged.previewMode !== "desktop_day") params.set("previewMode", merged.previewMode);
  if (merged.previewError) params.set("previewError", "1");
  if (merged.previewLoading) params.set("previewLoading", "1");
  if (merged.previewEmpty) params.set("previewEmpty", "1");

  const s = params.toString();
  return s ? `?${s}` : "";
}

export function defaultCmsQuery(): CmsQuery {
  return parseCmsQuery({});
}
