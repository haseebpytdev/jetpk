import { useMockData } from "@/lib/preview";
import {
  buildRolesPage,
  getAssignedUsersForRole,
  getRolePermissionKeys,
  getRoleValidationIssues,
} from "@/lib/roles/query-filters";
import { getRoleById, mockRoles } from "@/mocks/rbac-fixtures";
import type { RolesModuleResult, RolesQuery } from "@/types/roles";

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

export async function getRolesModule(query: RolesQuery): Promise<RolesModuleResult> {
  if (!useMockData()) {
    throw new RolesServiceError("Live role data is disabled in preview.", "ROL-PREVIEW-NO-LIVE");
  }

  if (query.previewError) {
    throw new RolesServiceError(
      "Mock roles service returned a recoverable error (preview simulation).",
      "ROL-PREVIEW-SIM-ERR",
    );
  }

  await new Promise((r) => setTimeout(r, 60));

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
    };
  }

  const sourceRoles = query.previewEmpty ? [] : mockRoles;
  const page = buildRolesPage(query, sourceRoles);
  const selectedRole = query.selected ? getRoleById(query.selected) ?? null : null;
  const selectedRolePermissionKeys = selectedRole ? getRolePermissionKeys(selectedRole.id) : [];
  const selectedRoleAssignedUsers = selectedRole ? getAssignedUsersForRole(selectedRole.id) : [];
  const validationIssues = selectedRole ? getRoleValidationIssues(selectedRole) : [];

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
    selectedRolePermissionKeys,
    selectedRoleAssignedUsers,
    validationIssues,
  };
}
