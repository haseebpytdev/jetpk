import {
  buildRolesPage,
  getAssignedUsersForRole,
  getRolePermissionKeys,
  getRoleValidationIssues,
} from "@/lib/roles/query-filters";
import { getRoleById, mockRoles } from "@/mocks/rbac-fixtures";
import type { Role } from "@/types/access-control";
import type { RolesModuleResult, RolesQuery } from "@/types/roles";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { createReadOnlyService, ReadOnlyServiceError, type ReadOnlyFetchOptions } from "@/lib/read-only/read-only-service";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import { transformRoleDetail, transformRolesModule } from "@/lib/read-only/laravel/transformers/roles";
import type { LaravelRbacMatrixPayload, LaravelRolesListPayload } from "@/lib/read-only/laravel/types";

export class RolesServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "RolesServiceError";
    this.referenceId = referenceId;
  }
}

const emptySummary = {
  totalRoles: 0,
  activeRoles: 0,
  protectedSystemRoles: 0,
  customRoles: 0,
  rolesWithHighRiskPermissions: 0,
  rolesRequiringReview: 0,
  unusedRoles: 0,
  incompleteRoles: 0,
};

function mapReadOnlyError(error: unknown): never {
  if (error instanceof ReadOnlyServiceError) {
    throw new RolesServiceError(error.envelope.error.message, error.envelope.error.referenceIdSafe);
  }
  throw error;
}

function toLaravelQuery(query: RolesQuery): Record<string, string | number> {
  return {
    page: query.page,
    pageSize: query.pageSize,
    q: query.search,
    category: query.category,
    status: query.status,
    roleType: query.roleType,
    protected: query.protected,
    risk: query.risk,
    validationState: query.validationState,
    channelScope: query.channelScope,
    assignedState: query.assignedState,
    sort: query.sort,
    direction: query.direction,
  };
}

function buildFixtureResult(query: RolesQuery): RolesModuleResult {
  if (query.previewLoading) {
    return {
      state: "loading",
      query,
      summary: emptySummary,
      table: { rows: [], total: 0, page: 1, pageSize: query.pageSize, pageCount: 1 },
      facets: { categories: [], statuses: [], scopes: [], validationStates: [] },
      selectedRole: null,
      selectedRolePermissionKeys: [],
      selectedRoleAssignedUsers: [],
      validationIssues: [],
      catalogPermissions: [],
    };
  }

  const sourceRoles = query.previewEmpty ? [] : mockRoles;
  const page = buildRolesPage(query, sourceRoles);
  const selectedRole = query.selected ? getRoleById(query.selected) ?? null : null;

  return {
    state: page.total === 0 ? "empty" : "ready",
    query,
    summary: page.summary,
    table: {
      rows: page.roles,
      total: page.total,
      page: page.page,
      pageSize: page.pageSize,
      pageCount: page.pageCount,
    },
    facets: page.facets,
    selectedRole,
    selectedRolePermissionKeys: selectedRole ? getRolePermissionKeys(selectedRole.id) : [],
    selectedRoleAssignedUsers: [],
    validationIssues: [],
    catalogPermissions: [],
  };
}

const rolesService = createReadOnlyService<RolesQuery, RolesModuleResult>({
  module: "roles",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(query, options) {
      if (query.previewError) {
        throw new ReadOnlyServiceError({
          error: {
            code: "internal_error",
            message: "Mock roles service returned a recoverable error (preview simulation).",
            referenceIdSafe: "ROL-PREVIEW-SIM-ERR",
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
      const [rolesEnvelope] = await Promise.all([
        fetchDashboardApi<LaravelRolesListPayload>(DASHBOARD_API_ROUTES.roles, {
          signal: options?.signal,
          query: toLaravelQuery(query),
        }),
        fetchDashboardApi<LaravelRbacMatrixPayload>(DASHBOARD_API_ROUTES.rbacMatrix, {
          signal: options?.signal,
          query: { domain: query.matrixDomain },
        }),
      ]);
      const pagination = rolesEnvelope.pagination ?? { page: 1, pageSize: 25, total: 0, pageCount: 1 };
      let selectedRole: Role | null = null;
      if (query.selected) {
        try {
          const detail = await fetchDashboardApi<Record<string, unknown>>(DASHBOARD_API_ROUTES.roleDetail(query.selected), {
            signal: options?.signal,
          });
          selectedRole = transformRoleDetail(detail.data);
        } catch (error) {
          if (!(error instanceof ReadOnlyServiceError && error.envelope.error.code === "not_found")) {
            throw error;
          }
        }
      }
      return {
        ...rolesEnvelope,
        data: transformRolesModule(rolesEnvelope.data, query, pagination, selectedRole),
      };
    },
  },
});

export async function getRolesModule(query: RolesQuery, options?: ReadOnlyFetchOptions): Promise<RolesModuleResult> {
  try {
    const envelope = await rolesService.fetchReadOnly(query, options);
    return envelope.data;
  } catch (error) {
    mapReadOnlyError(error);
  }
}

export async function getRoleDetail(id: string, options?: ReadOnlyFetchOptions): Promise<Role | null> {
  const { resolveDataSourceMode } = await import("@/lib/read-only/data-source");
  if (resolveDataSourceMode() === "fixture") {
    return getRoleById(id) ?? null;
  }
  try {
    const envelope = await fetchDashboardApi<Record<string, unknown>>(DASHBOARD_API_ROUTES.roleDetail(id), {
      signal: options?.signal,
    });
    return transformRoleDetail(envelope.data);
  } catch (error) {
    if (error instanceof ReadOnlyServiceError && error.envelope.error.code === "not_found") {
      return null;
    }
    mapReadOnlyError(error);
  }
}
