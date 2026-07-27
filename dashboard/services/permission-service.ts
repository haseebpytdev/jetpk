import { PERMISSION_CATALOG } from "@/lib/access-control/permission-catalog";
import { useMockData } from "@/lib/preview";
import {
  buildPermissionsPage,
  getAssignedRolesForPermission,
  getPermissionValidationIssues,
} from "@/lib/permissions/query-filters";
import { PERMISSION_BY_ID } from "@/lib/access-control/permission-catalog";
import type { PermissionsModuleResult, PermissionsQuery } from "@/types/permissions";

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

export async function getPermissionsModule(query: PermissionsQuery): Promise<PermissionsModuleResult> {
  if (!useMockData()) {
    throw new PermissionsServiceError("Live permission data is disabled in preview.", "PRM-PREVIEW-NO-LIVE");
  }

  if (query.previewError) {
    throw new PermissionsServiceError(
      "Mock permissions service returned a recoverable error (preview simulation).",
      "PRM-PREVIEW-SIM-ERR",
    );
  }

  await new Promise((r) => setTimeout(r, 60));

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
  const assignedRoles = selectedPermission ? getAssignedRolesForPermission(selectedPermission.key) : [];
  const validationIssues = selectedPermission ? getPermissionValidationIssues(selectedPermission) : [];

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
    assignedRoles,
    validationIssues,
  };
}
