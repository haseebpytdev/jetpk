import { getSectionDefinition } from "@/features/cms/registry/section-registry";
import { validateAsset, validateBanner, validateSectionInstance } from "@/features/cms/validation/cms-validation";
import { mergeValidationIssues } from "@/features/cms/validation/link-validation";
import {
  mockCmsAssets,
  mockCmsBanners,
  mockCmsNotices,
  mockCmsPages,
  mockCmsSections,
} from "@/mocks/cms-fixtures";
import type {
  CmsAsset,
  CmsBanner,
  CmsFacets,
  CmsNotice,
  CmsPage,
  CmsQuery,
  CmsSectionInstance,
  CmsValidationResult,
} from "@/types/cms";

export function buildCmsFacets(): CmsFacets {
  return {
    pageTypes: [...new Set(mockCmsPages.map((p) => p.pageType))].sort(),
    sectionTypes: [...new Set(mockCmsSections.map((s) => s.sectionType))].sort(),
    statuses: [...new Set(mockCmsPages.map((p) => p.status))].sort(),
    themeModes: [...new Set(mockCmsSections.map((s) => s.themeMode))].sort(),
    locales: [...new Set(mockCmsPages.map((p) => p.locale))].sort(),
    bannerFamilies: [...new Set(mockCmsBanners.map((b) => b.family))].sort(),
    noticeSeverities: [...new Set(mockCmsNotices.map((n) => n.severity))].sort(),
    assetStatuses: [...new Set(mockCmsAssets.map((a) => a.approvalStatus))].sort(),
    placements: [...new Set(mockCmsBanners.flatMap((b) => b.placements))].sort(),
    audiences: [...new Set(mockCmsNotices.map((n) => n.audience))].sort(),
  };
}

export function resolveValidation(record: { validation?: CmsValidationResult } & { id: string }, kind: "page" | "section" | "banner" | "notice" | "asset"): CmsValidationResult {
  if (kind === "asset") {
    const asset = record as CmsAsset;
    return mergeValidationIssues(validateAsset(asset));
  }
  if (kind === "banner") {
    const banner = record as CmsBanner;
    return mergeValidationIssues(validateBanner(banner));
  }
  if (kind === "section") {
    const section = record as CmsSectionInstance;
    const page = mockCmsPages.find((p) => p.id === section.pageId);
    const siblings = mockCmsSections.filter((s) => s.pageId === section.pageId);
    return mergeValidationIssues(validateSectionInstance(section, page?.pageType ?? "", siblings));
  }
  return record.validation ?? mergeValidationIssues([]);
}

function validationBucket(result: CmsValidationResult): "valid" | "warning" | "blocked" {
  if (result.issues.some((i) => i.blocking)) return "blocked";
  if (result.issues.some((i) => i.severity === "warning")) return "warning";
  return "valid";
}

function matchesSearch(text: string, search: string): boolean {
  if (!search) return true;
  return text.toLowerCase().includes(search.toLowerCase());
}

export function filterPages(query: CmsQuery): CmsPage[] {
  return mockCmsPages.filter((page) => {
    if (query.status !== "all" && page.status !== query.status) return false;
    if (query.pageType !== "all" && page.pageType !== query.pageType) return false;
    if (query.locale !== "all" && page.locale !== query.locale) return false;
    if (query.validationState !== "all") {
      const bucket = validationBucket(resolveValidation(page, "page"));
      if (bucket !== query.validationState) return false;
    }
    const haystack = `${page.id} ${page.title} ${page.slug} ${page.pageType}`;
    return matchesSearch(haystack, query.search);
  });
}

export function filterSections(query: CmsQuery): CmsSectionInstance[] {
  return mockCmsSections.filter((section) => {
    if (query.sectionType !== "all" && section.sectionType !== query.sectionType) return false;
    if (query.themeMode !== "all" && section.themeMode !== query.themeMode) return false;
    if (query.status !== "all") {
      const page = mockCmsPages.find((p) => p.id === section.pageId);
      if (!page || page.status !== query.status) return false;
    }
    if (query.validationState !== "all") {
      const bucket = validationBucket(resolveValidation(section, "section"));
      if (bucket !== query.validationState) return false;
    }
    const def = getSectionDefinition(section.sectionType);
    const page = mockCmsPages.find((p) => p.id === section.pageId);
    const haystack = `${section.id} ${section.sectionType} ${def?.label ?? ""} ${page?.title ?? ""}`;
    return matchesSearch(haystack, query.search);
  });
}

export function filterBanners(query: CmsQuery): CmsBanner[] {
  return mockCmsBanners.filter((banner) => {
    if (query.bannerFamily !== "all" && banner.family !== query.bannerFamily) return false;
    if (query.status !== "all" && banner.status !== query.status) return false;
    if (query.placement && !banner.placements.includes(query.placement)) return false;
    if (query.validationState !== "all") {
      const bucket = validationBucket(mergeValidationIssues(validateBanner(banner)));
      if (bucket !== query.validationState) return false;
    }
    const haystack = `${banner.id} ${banner.title} ${banner.family}`;
    return matchesSearch(haystack, query.search);
  });
}

export function filterNotices(query: CmsQuery): CmsNotice[] {
  return mockCmsNotices.filter((notice) => {
    if (query.noticeSeverity !== "all" && notice.severity !== query.noticeSeverity) return false;
    if (query.status !== "all" && notice.status !== query.status) return false;
    if (query.audience !== "all" && notice.audience !== query.audience) return false;
    if (query.locale !== "all" && notice.locale !== query.locale) return false;
    if (query.placement && notice.placement !== query.placement) return false;
    if (query.validationState !== "all") {
      const bucket = validationBucket(notice.validation);
      if (bucket !== query.validationState) return false;
    }
    const haystack = `${notice.id} ${notice.title} ${notice.message}`;
    return matchesSearch(haystack, query.search);
  });
}

export function filterAssets(query: CmsQuery): CmsAsset[] {
  return mockCmsAssets.filter((asset) => {
    if (query.assetStatus !== "all" && asset.approvalStatus !== query.assetStatus) return false;
    if (query.validationState !== "all") {
      const bucket = validationBucket(mergeValidationIssues(validateAsset(asset)));
      if (bucket !== query.validationState) return false;
    }
    if (query.themeMode === "night" && !asset.nightVariant) return false;
    if (query.themeMode === "day" && !asset.dayVariant) return false;
    const haystack = `${asset.id} ${asset.internalName} ${asset.category}`;
    return matchesSearch(haystack, query.search);
  });
}

export function sortRows<T extends Record<string, unknown>>(rows: T[], sort: string, direction: "asc" | "desc"): T[] {
  const sorted = [...rows].sort((a, b) => {
    const av = a[sort];
    const bv = b[sort];
    if (typeof av === "number" && typeof bv === "number") return av - bv;
    return String(av ?? "").localeCompare(String(bv ?? ""));
  });
  return direction === "desc" ? sorted.reverse() : sorted;
}

export function paginate<T>(items: T[], page: number, pageSize: number): { items: T[]; total: number; page: number; pageCount: number } {
  const total = items.length;
  const pageCount = Math.max(1, Math.ceil(total / pageSize));
  const safePage = Math.min(page, pageCount);
  const start = (safePage - 1) * pageSize;
  return { items: items.slice(start, start + pageSize), total, page: safePage, pageCount };
}
