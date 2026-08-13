import type { Role, RoleCategory, RoleScope, RoleStatus, ValidationState } from "@/types/access-control";
import type { SortDirection } from "@/types/users";

export type RoleSortField =
  | "name"
  | "category"
  | "status"
  | "assignedUserCount"
  | "permissionCount"
  | "highRiskPermissionCount"
  | "updatedAt"
  | "validationState";

export type RoleTypeFilter = "all" | "system" | "custom";
export type ProtectedFilter = "all" | "protected" | "unprotected";
export type RoleRiskFilter = "all" | "highRisk" | "noHighRisk";
export type AssignedStateFilter = "all" | "assigned" | "unassigned" | "unused";
export type ChannelScopeFilter = RoleScope | "all";

export type RolesQuery = {
  search: string;
  category: RoleCategory | "all";
  status: RoleStatus | "all";
  roleType: RoleTypeFilter;
  protected: ProtectedFilter;
  risk: RoleRiskFilter;
  validationState: ValidationState | "all";
  channelScope: ChannelScopeFilter;
  assignedState: AssignedStateFilter;
  page: number;
  pageSize: number;
  sort: RoleSortField;
  direction: SortDirection;
  selected: string | null;
  compareA: string | null;
  compareB: string | null;
  matrixDomain: string;
  matrixRole: string;
  state: string;
  previewError: boolean;
  previewLoading: boolean;
  previewEmpty: boolean;
};

export type RolesSummaryMetrics = {
  totalRoles: number;
  activeRoles: number;
  protectedSystemRoles: number;
  customRoles: number;
  rolesWithHighRiskPermissions: number;
  rolesRequiringReview: number;
  unusedRoles: number;
  incompleteRoles: number;
};

export type RoleTableRow = {
  id: string;
  name: string;
  description: string;
  category: RoleCategory;
  categoryLabel: string;
  isSystem: boolean;
  isProtected: boolean;
  assignedUserCount: number;
  permissionCount: number;
  highRiskPermissionCount: number;
  scope: RoleScope;
  scopeLabel: string;
  status: RoleStatus;
  validationState: ValidationState;
  updatedAt: string;
};

export type RolesPageResult = {
  roles: RoleTableRow[];
  total: number;
  page: number;
  pageSize: number;
  pageCount: number;
  summary: RolesSummaryMetrics;
  facets: {
    categories: RoleCategory[];
    statuses: RoleStatus[];
    scopes: RoleScope[];
    validationStates: ValidationState[];
  };
};

export type RolesModuleResult = {
  state: "ready" | "loading" | "empty" | "error";
  query: RolesQuery;
  summary: RolesSummaryMetrics;
  table: {
    rows: RoleTableRow[];
    total: number;
    page: number;
    pageSize: number;
    pageCount: number;
  };
  facets: RolesPageResult["facets"];
  selectedRole: Role | null;
  selectedRolePermissionKeys: string[];
  selectedRoleAssignedUsers: { id: string; name: string }[];
  validationIssues: import("@/types/access-control").AccessValidationIssue[];
  catalogPermissions: Array<{ key: string; label: string; category: string; highRisk?: boolean }>;
};

export type RoleComparisonResult = {
  roleA: Role;
  roleB: Role;
  permissionCountA: number;
  permissionCountB: number;
  domainCoverageA: number;
  domainCoverageB: number;
  viewAccessA: number;
  viewAccessB: number;
  requestAccessA: number;
  requestAccessB: number;
  approvalAccessA: number;
  approvalAccessB: number;
  manageAccessA: number;
  manageAccessB: number;
  exportAccessA: number;
  exportAccessB: number;
  highRiskA: string[];
  highRiskB: string[];
  channelScopesA: RoleScope[];
  channelScopesB: RoleScope[];
  uniqueToA: string[];
  uniqueToB: string[];
  shared: string[];
};
