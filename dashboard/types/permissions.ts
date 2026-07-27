import type {
  ActionType,
  Permission,
  PermissionEffect,
  PermissionGroup,
  PermissionRisk,
  PermissionScope,
  ValidationState,
} from "@/types/access-control";
import type { SortDirection } from "@/types/users";

export type PermissionSortField =
  | "key"
  | "domain"
  | "action"
  | "risk"
  | "assignedRoleCount"
  | "validationState";

export type PrerequisiteFilter = "all" | "hasPrerequisite" | "noPrerequisite" | "missingPrerequisite";
export type AssignedRoleStateFilter = "all" | "assigned" | "unassigned";
export type ScopeFilter = PermissionScope | "all";

export type PermissionsQuery = {
  search: string;
  domain: PermissionGroup | "all";
  action: ActionType | "all";
  risk: PermissionRisk | "all";
  effect: PermissionEffect | "all";
  scope: ScopeFilter;
  prerequisite: PrerequisiteFilter;
  assignedState: AssignedRoleStateFilter;
  validationState: ValidationState | "all";
  page: number;
  pageSize: number;
  sort: PermissionSortField;
  direction: SortDirection;
  selected: string | null;
  state: string;
  previewError: boolean;
  previewLoading: boolean;
  previewEmpty: boolean;
};

export type PermissionsSummaryMetrics = {
  totalPermissions: number;
  viewPermissions: number;
  requestPermissions: number;
  approvalPermissions: number;
  managePermissions: number;
  exportPermissions: number;
  highRiskPermissions: number;
  permissionsRequiringPrerequisiteReview: number;
};

export type PermissionTableRow = {
  id: string;
  key: string;
  domain: PermissionGroup;
  domainLabel: string;
  action: ActionType;
  label: string;
  description: string;
  risk: PermissionRisk;
  isHighRisk: boolean;
  prerequisiteKey: string | null;
  supportedScopes: PermissionScope[];
  assignedRoleCount: number;
  validationState: ValidationState;
  laravelPolicyHint: string;
};

export type PermissionsPageResult = {
  permissions: PermissionTableRow[];
  total: number;
  page: number;
  pageSize: number;
  pageCount: number;
  summary: PermissionsSummaryMetrics;
  facets: {
    domains: PermissionGroup[];
    actions: ActionType[];
    risks: PermissionRisk[];
    scopes: PermissionScope[];
  };
};

export type PermissionsModuleResult = {
  state: "ready" | "loading" | "empty" | "error";
  query: PermissionsQuery;
  summary: PermissionsSummaryMetrics;
  table: {
    rows: PermissionTableRow[];
    total: number;
    page: number;
    pageSize: number;
    pageCount: number;
  };
  facets: PermissionsPageResult["facets"];
  selectedPermission: Permission | null;
  assignedRoles: { id: string; name: string }[];
  validationIssues: import("@/types/access-control").AccessValidationIssue[];
};
