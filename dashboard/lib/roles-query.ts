import type { RoleCategory, RoleScope, RoleStatus, ValidationState } from "@/types/access-control";
import type { AssignedStateFilter, ChannelScopeFilter, ProtectedFilter, RoleRiskFilter, RoleSortField, RoleTypeFilter, RolesQuery } from "@/types/roles";

const DEFAULT_PAGE_SIZE = 20;

const ROLE_CATEGORIES: RoleCategory[] = ["system", "operations", "finance", "content", "analytics", "audit", "custom"];
const ROLE_STATUSES: RoleStatus[] = ["active", "inactive", "deprecated", "draft"];
const ROLE_SCOPES: RoleScope[] = ["allChannels", "gdsOnly", "ndcOnly", "specificSupplier", "assignedBranch", "ownRecords", "allRecords"];
const VALIDATION_STATES: ValidationState[] = ["valid", "warning", "blocked", "review"];
const SORT_FIELDS: RoleSortField[] = [
  "name",
  "category",
  "status",
  "assignedUserCount",
  "permissionCount",
  "highRiskPermissionCount",
  "updatedAt",
  "validationState",
];

function first(value: string | string[] | undefined): string {
  if (Array.isArray(value)) return value[0] ?? "";
  return value ?? "";
}

function parseEnum<T extends string>(raw: string, allowed: readonly T[], fallback: T | "all"): T | "all" {
  if (!raw || raw === "all") return fallback;
  return (allowed as readonly string[]).includes(raw) ? (raw as T) : fallback;
}

function parsePositiveInt(raw: string, fallback: number, max?: number): number {
  const n = Number.parseInt(raw, 10);
  if (!Number.isFinite(n) || n < 1) return fallback;
  if (max !== undefined && n > max) return max;
  return n;
}

function parsePageSize(raw: string): number {
  const n = parsePositiveInt(raw, DEFAULT_PAGE_SIZE);
  if (n === 10 || n === 20 || n === 50) return n;
  return DEFAULT_PAGE_SIZE;
}

function parseRoleType(raw: string): RoleTypeFilter {
  if (raw === "system" || raw === "custom") return raw;
  return "all";
}

function parseProtected(raw: string): ProtectedFilter {
  if (raw === "protected" || raw === "unprotected") return raw;
  return "all";
}

function parseRisk(raw: string): RoleRiskFilter {
  if (raw === "highRisk" || raw === "noHighRisk") return raw;
  return "all";
}

function parseAssignedState(raw: string): AssignedStateFilter {
  if (raw === "assigned" || raw === "unassigned" || raw === "unused") return raw;
  return "all";
}

function parseChannelScope(raw: string): ChannelScopeFilter {
  if (!raw || raw === "all") return "all";
  return (ROLE_SCOPES as readonly string[]).includes(raw) ? (raw as RoleScope) : "all";
}

export function parseRolesQuery(
  searchParams: Record<string, string | string[] | undefined>,
): RolesQuery {
  const sortRaw = first(searchParams.sort);
  const directionRaw = first(searchParams.direction);

  return {
    search: first(searchParams.search).trim() || first(searchParams.q).trim(),
    category: parseEnum(first(searchParams.category), ROLE_CATEGORIES, "all"),
    status: parseEnum(first(searchParams.status), ROLE_STATUSES, "all"),
    roleType: parseRoleType(first(searchParams.roleType)),
    protected: parseProtected(first(searchParams.protected)),
    risk: parseRisk(first(searchParams.risk)),
    validationState: parseEnum(first(searchParams.validationState), VALIDATION_STATES, "all"),
    channelScope: parseChannelScope(first(searchParams.channelScope)),
    assignedState: parseAssignedState(first(searchParams.assignedState)),
    page: parsePositiveInt(first(searchParams.page), 1),
    pageSize: parsePageSize(first(searchParams.pageSize)),
    sort: SORT_FIELDS.includes(sortRaw as RoleSortField) ? (sortRaw as RoleSortField) : "name",
    direction: directionRaw === "asc" || directionRaw === "desc" ? directionRaw : "asc",
    selected: first(searchParams.selected) || null,
    compareA: first(searchParams.compareA) || null,
    compareB: first(searchParams.compareB) || null,
    matrixDomain: first(searchParams.matrixDomain),
    matrixRole: first(searchParams.matrixRole),
    state: first(searchParams.state),
    previewError: first(searchParams.previewError) === "1",
    previewLoading: first(searchParams.previewLoading) === "1",
    previewEmpty: first(searchParams.previewEmpty) === "1",
  };
}

export function rolesQueryToSearchParams(query: RolesQuery, overrides?: Partial<RolesQuery>): string {
  const merged = { ...query, ...overrides };
  const params = new URLSearchParams();

  if (merged.search) params.set("search", merged.search);
  if (merged.category !== "all") params.set("category", merged.category);
  if (merged.status !== "all") params.set("status", merged.status);
  if (merged.roleType !== "all") params.set("roleType", merged.roleType);
  if (merged.protected !== "all") params.set("protected", merged.protected);
  if (merged.risk !== "all") params.set("risk", merged.risk);
  if (merged.validationState !== "all") params.set("validationState", merged.validationState);
  if (merged.channelScope !== "all") params.set("channelScope", merged.channelScope);
  if (merged.assignedState !== "all") params.set("assignedState", merged.assignedState);
  if (merged.page > 1) params.set("page", String(merged.page));
  if (merged.pageSize !== DEFAULT_PAGE_SIZE) params.set("pageSize", String(merged.pageSize));
  if (merged.sort !== "name") params.set("sort", merged.sort);
  if (merged.direction !== "asc") params.set("direction", merged.direction);
  if (merged.selected) params.set("selected", merged.selected);
  if (merged.compareA) params.set("compareA", merged.compareA);
  if (merged.compareB) params.set("compareB", merged.compareB);
  if (merged.matrixDomain) params.set("matrixDomain", merged.matrixDomain);
  if (merged.matrixRole) params.set("matrixRole", merged.matrixRole);
  if (merged.state) params.set("state", merged.state);
  if (merged.previewError) params.set("previewError", "1");
  if (merged.previewLoading) params.set("previewLoading", "1");
  if (merged.previewEmpty) params.set("previewEmpty", "1");

  const s = params.toString();
  return s ? `?${s}` : "";
}

export function defaultRolesQuery(): RolesQuery {
  return parseRolesQuery({});
}
