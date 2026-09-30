import { CMS_BRAND, type CmsAsset, type CmsFoundationResult, type CmsModuleKey, type CmsModuleResult, type CmsPage, type CmsQuery } from "@/types/cms";
import { CMS_FIXTURE_COUNTS } from "@/mocks/cms-fixtures";
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

const LIVE_SUPPORTED_MODULES: CmsModuleKey[] = ["overview", "pages", "assets"];

function mapLiveAsset(row: Record<string, unknown>): CmsAsset {
  const desktop = (row.desktop as CmsAsset["desktop"]) ?? {
    width: 0,
    height: 0,
    aspectRatio: "—",
    placeholderLabel: String(row.internalName ?? "asset"),
  };
  const rawValidation = row.validation as { issues?: CmsAsset["validation"]["issues"] } | undefined;
  const issues = Array.isArray(rawValidation?.issues) ? rawValidation.issues : [];
  const validation = { valid: issues.length === 0, issues };
  const fileTypeRaw = String(row.fileType ?? "image/jpeg");
  const fileType =
    fileTypeRaw === "image/png" || fileTypeRaw === "image/webp" || fileTypeRaw === "image/jpeg"
      ? fileTypeRaw
      : "image/jpeg";
  const categoryRaw = String(row.category ?? "general");
  const allowed = [
    "hero",
    "support",
    "offer",
    "destination",
    "airline",
    "campaign",
    "notice",
    "general",
  ] as const;
  const category = (allowed as readonly string[]).includes(categoryRaw)
    ? (categoryRaw as CmsAsset["category"])
    : "general";

  return {
    id: String(row.id ?? ""),
    internalName: String(row.internalName ?? ""),
    category,
    desktop,
    mobile: (row.mobile as CmsAsset["mobile"]) ?? desktop,
    dayVariant: (row.dayVariant as CmsAsset["dayVariant"]) ?? null,
    nightVariant: (row.nightVariant as CmsAsset["nightVariant"]) ?? null,
    fileType,
    altText: String(row.altText ?? ""),
    focalPointX: Number(row.focalPointX ?? 50),
    focalPointY: Number(row.focalPointY ?? 50),
    safeArea: String(row.safeArea ?? "n/a"),
    approvalStatus: "approved",
    usageCount: Number(row.usageCount ?? 0),
    createdDate: String(row.createdDate ?? ""),
    updatedDate: String(row.updatedDate ?? ""),
    authorId: String(row.authorId ?? "—"),
    validation,
  };
}

function transformLiveAssetsModule(
  assets: CmsAsset[],
  query: CmsQuery,
  pagination: { page: number; pageSize: number; total: number; pageCount: number },
): CmsModuleResult {
  const selectedAsset =
    query.selected != null ? (assets.find((asset) => asset.id === query.selected) ?? null) : null;
  const rows = assets.map((asset) => ({
    id: asset.id,
    internalName: asset.internalName,
    category: asset.category,
    fileType: asset.fileType,
    width: asset.desktop.width,
    height: asset.desktop.height,
    aspectRatio: asset.desktop.aspectRatio,
    desktop: "Yes",
    mobile: "Yes",
    day: "No",
    night: "No",
    altText: asset.altText.trim() ? "Present" : "Missing",
    focalPoint: `${asset.focalPointX}, ${asset.focalPointY}`,
    safeArea: asset.safeArea,
    approval: asset.approvalStatus,
    usageCount: asset.usageCount,
    validation: asset.validation.issues.length ? "Warning" : "Valid",
    createdDate: asset.createdDate,
    updatedDate: asset.updatedDate,
    author: asset.authorId,
    href: `/cms/assets?selected=${encodeURIComponent(asset.id)}`,
  }));

  return {
    state: assets.length === 0 ? "empty" : "ready",
    module: "assets",
    query,
    brand: CMS_BRAND,
    metrics: [],
    validationSummary: {
      valid: assets.filter((a) => a.validation.issues.length === 0).length,
      warning: assets.filter((a) => a.validation.issues.length > 0).length,
      blocked: 0,
    },
    distributions: { publication: [], contentType: [], validation: [], theme: [], assets: [] },
    attentionQueue: [],
    recentRevisions: [],
    scheduledQueue: [],
    reviewQueue: [],
    table: {
      columns: [
        { key: "id", label: "Asset ID", sortable: true },
        { key: "internalName", label: "Internal name", sortable: true },
        { key: "category", label: "Category" },
        { key: "fileType", label: "File type" },
        { key: "altText", label: "Alt text" },
        { key: "approval", label: "Approval" },
        { key: "updatedDate", label: "Updated", sortable: true },
      ],
      rows,
      total: pagination.total,
      page: pagination.page,
      pageSize: pagination.pageSize,
      pageCount: pagination.pageCount,
    },
    selectedPage: null,
    selectedSection: null,
    selectedBanner: null,
    selectedNotice: null,
    selectedAsset,
    facets: {
      pageTypes: [],
      sectionTypes: [],
      statuses: [],
      themeModes: [],
      locales: [],
      bannerFamilies: [],
      noticeSeverities: [],
      assetStatuses: ["approved"],
      placements: [],
      audiences: [],
    },
  };
}

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
    mode: "laravelLive",
    async fetch({ query, module }, options) {
      if (!LIVE_SUPPORTED_MODULES.includes(module)) {
        throw new ReadOnlyServiceError({
          error: {
            code: "unavailable",
            referenceIdSafe: "CMS-LIVE-MODULE-UNAVAILABLE",
            message: `Live CMS does not expose the ${module} submodule yet.`,
          },
          meta: { source: "laravelLive", schemaVersion: "dash-read-only-v1" },
        });
      }

      if (module === "assets") {
        const envelope = await fetchDashboardApi<{ assets?: Record<string, unknown>[] }>(
          DASHBOARD_API_ROUTES.cmsAssets,
          {
            signal: options?.signal,
            query: {
              page: query.page,
              pageSize: query.pageSize,
              q: query.search,
            },
          },
        );
        const pagination = envelope.pagination ?? { page: 1, pageSize: 25, total: 0, pageCount: 1 };
        const assets = (envelope.data.assets ?? []).map((row) => mapLiveAsset(row));
        return {
          ...envelope,
          data: transformLiveAssetsModule(assets, query, pagination),
        };
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
