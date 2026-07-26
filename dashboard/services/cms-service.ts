import { CMS_BRAND } from "@/types/cms";
import type { CmsFoundationResult, CmsModuleKey, CmsModuleResult, CmsQuery } from "@/types/cms";
import { buildCmsModule } from "@/lib/cms/build-cms-module";
import {
  CMS_FIXTURE_COUNTS,
  mockCmsAssets,
  mockCmsBanners,
  mockCmsNotices,
  mockCmsPages,
  mockCmsRevisions,
  mockCmsSections,
} from "@/mocks/cms-fixtures";
import { useMockData } from "@/lib/preview";

export class CmsServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "CmsServiceError";
    this.referenceId = referenceId;
  }
}

export async function getCmsFoundation(query: CmsQuery, module: CmsModuleKey): Promise<CmsFoundationResult> {
  const result = await getCmsModule(query, module);
  return {
    state: result.state,
    brand: result.brand,
    counts: CMS_FIXTURE_COUNTS,
    validationSummary: result.validationSummary,
  };
}

export async function getCmsModule(query: CmsQuery, module: CmsModuleKey): Promise<CmsModuleResult> {
  if (!useMockData()) {
    throw new CmsServiceError("Live CMS data is disabled in preview.", "CMS-PREVIEW-NO-LIVE");
  }

  if (query.previewError) {
    throw new CmsServiceError(
      "Mock CMS service returned a recoverable error (preview simulation).",
      "CMS-PREVIEW-SIM-ERR",
    );
  }

  await new Promise((r) => setTimeout(r, module === "overview" ? 60 : 40));

  if (query.previewLoading) {
    return {
      state: "loading",
      module,
      query,
      brand: CMS_BRAND,
      metrics: [],
      validationSummary: { valid: 0, warning: 0, blocked: 0 },
      distributions: { publication: [], contentType: [], validation: [], theme: [], assets: [] },
      attentionQueue: [],
      recentRevisions: [],
      scheduledQueue: [],
      reviewQueue: [],
      table: { columns: [], rows: [], total: 0, page: 1, pageSize: query.pageSize, pageCount: 1 },
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
        locales: [],
        bannerFamilies: [],
        noticeSeverities: [],
        assetStatuses: [],
        placements: [],
        audiences: [],
      },
    };
  }

  const result = buildCmsModule(module, query);

  if (query.previewEmpty) {
    return {
      ...result,
      state: "empty",
      metrics: [],
      table: { ...result.table, rows: [], total: 0 },
      attentionQueue: [],
    };
  }

  return result;
}

export function listCmsPages() {
  return mockCmsPages;
}

export function listCmsSections() {
  return mockCmsSections;
}

export function listCmsBanners() {
  return mockCmsBanners;
}

export function listCmsNotices() {
  return mockCmsNotices;
}

export function listCmsAssets() {
  return mockCmsAssets;
}

export function listCmsRevisions() {
  return mockCmsRevisions;
}
