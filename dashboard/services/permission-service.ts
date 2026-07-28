import { PERMISSION_CATALOG, PERMISSION_BY_ID } from "@/lib/access-control/permission-catalog";
import {
  buildPermissionsPage,
  getAssignedRolesForPermission,
  getPermissionValidationIssues,
} from "@/lib/permissions/query-filters";
import type { Permission } from "@/types/access-control";
import type { PermissionsModuleResult, PermissionsQuery } from "@/types/permissions";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { createReadOnlyService, ReadOnlyServiceError, type ReadOnlyFetchOptions } from "@/lib/read-only/read-only-service";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import { transformPermissionDetail, transformPermissionsModule } from "@/lib/read-only/laravel/transformers/permissions";
import type { LaravelPermissionsListPayload } from "@/lib/read-only/laravel/types";

export class PermissionsServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "PermissionsServiceError";
    this.referenceId = referenceId;
  }
}

const emptySummary = {
  totalPermissions: 0,
  viewPermissions: 0,
  requestPermissions: 0,
  approvalPermissions: 0,
  managePermissions: 0,
  exportPermissions: 0,
  highRiskPermissions: 0,
  permissionsRequiringPrerequisiteReview: 0,
};

function mapReadOnlyError(error: unknown): never {
  if (error instanceof ReadOnlyServiceError) {
    throw new PermissionsServiceError(error.envelope.error.message, error.envelope.error.referenceIdSafe);
  }
  throw error;
}

function toLaravelQuery(query: PermissionsQuery): Record<string, string | number> {
  return {
    page: query.page,
    pageSize: query.pageSize,
    q: query.search,
    domain: query.domain,
    action: query.action,
    risk: query.risk,
    scope: query.scope,
    validationState: query.validationState,
    sort: query.sort,
    direction: query.direction,
  };
}

function buildFixtureResult(query: PermissionsQuery): PermissionsModuleResult {
  if (query.previewLoading) {
    return {
      state: "loading",
      query,
      summary: emptySummary,
      table: { rows: [], total: 0, page: 1, pageSize: query.pageSize, pageCount: 1 },
      facets: { domains: [], actions: [], risks: [], scopes: [] },
      selectedPermission: null,
      assignedRoles: [],
      validationIssues: [],
    };
  }

  const source = query.previewEmpty ? [] : PERMISSION_CATALOG;
  const page = buildPermissionsPage(query, source);
  const selectedPermission = query.selected ? PERMISSION_BY_ID.get(query.selected) ?? null : null;

  return {
    state: page.total === 0 ? "empty" : "ready",
    query,
    summary: page.summary,
    table: {
      rows: page.permissions,
      total: page.total,
      page: page.page,
      pageSize: page.pageSize,
      pageCount: page.pageCount,
    },
    facets: page.facets,
    selectedPermission,
    assignedRoles: selectedPermission ? getAssignedRolesForPermission(selectedPermission.key) : [],
    validationIssues: selectedPermission ? getPermissionValidationIssues(selectedPermission) : [],
  };
}

const permissionsService = createReadOnlyService<PermissionsQuery, PermissionsModuleResult>({
  module: "permissions",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(query, options) {
      if (query.previewError) {
        throw new ReadOnlyServiceError({
          error: {
            code: "internal_error",
            message: "Mock permissions service returned a recoverable error (preview simulation).",
            referenceIdSafe: "PRM-PREVIEW-SIM-ERR",
          },
          meta: { source: "fixture", schemaVersion: "dash-read-only-v1" },
        });
      }
      await new Promise((r) => setTimeout(r, 60));
      return createReadOnlyEnvelope({ data: buildFixtureResult(query), metadata: options?.metadata });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(query, options) {
      const envelope = await fetchDashboardApi<LaravelPermissionsListPayload>(DASHBOARD_API_ROUTES.permissions, {
        signal: options?.signal,
        query: toLaravelQuery(query),
      });
      const pagination = envelope.pagination ?? { page: 1, pageSize: 25, total: 0, pageCount: 1 };
      let selectedPermission: Permission | null = null;
      if (query.selected) {
        try {
          const detail = await fetchDashboardApi<Record<string, unknown>>(
            DASHBOARD_API_ROUTES.permissionDetail(query.selected),
            { signal: options?.signal },
          );
          selectedPermission = transformPermissionDetail(detail.data);
        } catch (error) {
          if (!(error instanceof ReadOnlyServiceError && error.envelope.error.code === "not_found")) {
            throw error;
          }
        }
      }
      return {
        ...envelope,
        data: transformPermissionsModule(envelope.data, query, pagination, selectedPermission),
      };
    },
  },
});

export async function getPermissionsModule(
  query: PermissionsQuery,
  options?: ReadOnlyFetchOptions,
): Promise<PermissionsModuleResult> {
  try {
    const envelope = await permissionsService.fetchReadOnly(query, options);
    return envelope.data;
  } catch (error) {
    mapReadOnlyError(error);
  }
}
