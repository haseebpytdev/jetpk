import { CMS_BRAND } from "@/types/cms";
import { CMS_FIXTURE_COUNTS } from "@/mocks/cms-fixtures";
import type { CmsFoundationResult, CmsModuleKey, CmsModuleResult, CmsPage, CmsQuery } from "@/types/cms";
import { buildCmsModule } from "@/lib/cms/build-cms-module";
import {
  mockCmsAssets,
  mockCmsBanners,
  mockCmsNotices,
  mockCmsPages,
  mockCmsRevisions,
  mockCmsSections,
} from "@/mocks/cms-fixtures";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { createReadOnlyService, ReadOnlyServiceError, type ReadOnlyFetchOptions } from "@/lib/read-only/read-only-service";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import { transformCmsModule, mapCmsPage } from "@/lib/read-only/laravel/transformers/cms";
import type { LaravelCmsPagesListPayload } from "@/lib/read-only/laravel/types";

export class CmsServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "CmsServiceError";
    this.referenceId = referenceId;
  }
}

const LIVE_SUPPORTED_MODULES: CmsModuleKey[] = ["overview", "pages"];

function mapReadOnlyError(error: unknown): never {
  if (error instanceof ReadOnlyServiceError) {
    throw new CmsServiceError(error.envelope.error.message, error.envelope.error.referenceIdSafe);
  }
  throw error;
}

function toLaravelQuery(query: CmsQuery): Record<string, string | number> {
  return {
    page: query.page,
    pageSize: query.pageSize,
    q: query.search,
    status: query.status,
    pageType: query.pageType,
    validationState: query.validationState,
    theme: query.themeMode,
    sort: query.sort,
    direction: query.direction,
  };
}

function buildFixtureModule(query: CmsQuery, module: CmsModuleKey): CmsModuleResult {
  if (query.previewError) {
    throw new ReadOnlyServiceError({
      error: {
        code: "internal_error",
        message: "Mock CMS service returned a recoverable error (preview simulation).",
        referenceIdSafe: "CMS-PREVIEW-SIM-ERR",
      },
      meta: { source: "fixture", schemaVersion: "dash-read-only-v1" },
    });
  }

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

const cmsService = createReadOnlyService<{ query: CmsQuery; module: CmsModuleKey }, CmsModuleResult>({
  module: "cms",
  fixtureAdapter: {
    mode: "fixture",
    async fetch({ query, module }, options) {
      await new Promise((r) => setTimeout(r, module === "overview" ? 60 : 40));
      return createReadOnlyEnvelope({ data: buildFixtureModule(query, module), metadata: options?.metadata });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch({ query, module }, options) {
      if (!LIVE_SUPPORTED_MODULES.includes(module)) {
        throw new ReadOnlyServiceError({
          error: {
            code: "unavailable",
            referenceIdSafe: "CMS-LIVE-MODULE-UNAVAILABLE",
            message: `Laravel read-only CMS does not expose the ${module} submodule yet.`,
          },
          meta: { source: "laravelReadOnly", schemaVersion: "dash-read-only-v1" },
        });
      }

      const envelope = await fetchDashboardApi<LaravelCmsPagesListPayload>(DASHBOARD_API_ROUTES.cmsPages, {
        signal: options?.signal,
        query: toLaravelQuery(query),
      });
      const pagination = envelope.pagination ?? { page: 1, pageSize: 25, total: 0, pageCount: 1 };

      let selectedPage: CmsPage | null = null;
      if (query.selected) {
        try {
          const detail = await fetchDashboardApi<Record<string, unknown>>(DASHBOARD_API_ROUTES.cmsPageDetail(query.selected), {
            signal: options?.signal,
          });
          selectedPage = mapCmsPage(detail.data);
        } catch (error) {
          if (!(error instanceof ReadOnlyServiceError && error.envelope.error.code === "not_found")) {
            throw error;
          }
        }
      }

      return {
        ...envelope,
        data: transformCmsModule(envelope.data, query, module, pagination, selectedPage),
      };
    },
  },
});

export async function getCmsFoundation(query: CmsQuery, module: CmsModuleKey): Promise<CmsFoundationResult> {
  const result = await getCmsModule(query, module);
  return {
    state: result.state,
    brand: result.brand,
    counts: CMS_FIXTURE_COUNTS,
    validationSummary: result.validationSummary,
  };
}

export async function getCmsModule(
  query: CmsQuery,
  module: CmsModuleKey,
  options?: ReadOnlyFetchOptions,
): Promise<CmsModuleResult> {
  try {
    const envelope = await cmsService.fetchReadOnly({ query, module }, options);
    return envelope.data;
  } catch (error) {
    mapReadOnlyError(error);
  }
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
