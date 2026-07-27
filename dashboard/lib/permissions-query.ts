import type { ActionType, PermissionGroup, PermissionRisk, PermissionScope, ValidationState } from "@/types/access-control";
import type { AssignedRoleStateFilter, PermissionSortField, PermissionsQuery, PrerequisiteFilter, ScopeFilter } from "@/types/permissions";

const DEFAULT_PAGE_SIZE = 20;

const DOMAINS: PermissionGroup[] = [
  "dashboard", "bookings", "payments", "customers", "suppliers", "agents",
  "pnrs", "tickets", "reports", "cms", "users", "roles", "settings", "audit",
];
const ACTIONS: ActionType[] = ["view", "create", "update", "request", "approve", "manage", "export", "invite", "suspend", "assign"];
const RISKS: PermissionRisk[] = ["standard", "elevated", "high"];
const SCOPES: PermissionScope[] = ["all", "own", "branch", "supplier", "channel:gds", "channel:ndc", "channel:oneApi", "channel:manual", "channel:mock"];
const VALIDATION_STATES: ValidationState[] = ["valid", "warning", "blocked", "review"];
const SORT_FIELDS: PermissionSortField[] = ["key", "domain", "action", "risk", "assignedRoleCount", "validationState"];

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

function parsePrerequisite(raw: string): PrerequisiteFilter {
  if (raw === "hasPrerequisite" || raw === "noPrerequisite" || raw === "missingPrerequisite") return raw;
  return "all";
}

function parseAssignedState(raw: string): AssignedRoleStateFilter {
  if (raw === "assigned" || raw === "unassigned") return raw;
  return "all";
}

function parseScope(raw: string): ScopeFilter {
  if (!raw || raw === "all") return "all";
  return (SCOPES as readonly string[]).includes(raw) ? (raw as PermissionScope) : "all";
}

export function parsePermissionsQuery(
  searchParams: Record<string, string | string[] | undefined>,
): PermissionsQuery {
  const sortRaw = first(searchParams.sort);
  const directionRaw = first(searchParams.direction);
  const effectRaw = first(searchParams.effect);

  return {
    search: first(searchParams.search).trim() || first(searchParams.q).trim(),
    domain: parseEnum(first(searchParams.domain), DOMAINS, "all"),
    action: parseEnum(first(searchParams.action), ACTIONS, "all"),
    risk: parseEnum(first(searchParams.risk), RISKS, "all"),
    effect: effectRaw === "allow" || effectRaw === "deny" ? effectRaw : "all",
    scope: parseScope(first(searchParams.scope)),
    prerequisite: parsePrerequisite(first(searchParams.prerequisite)),
    assignedState: parseAssignedState(first(searchParams.assignedState)),
    validationState: parseEnum(first(searchParams.validationState), VALIDATION_STATES, "all"),
    page: parsePositiveInt(first(searchParams.page), 1),
    pageSize: parsePageSize(first(searchParams.pageSize)),
    sort: SORT_FIELDS.includes(sortRaw as PermissionSortField) ? (sortRaw as PermissionSortField) : "key",
    direction: directionRaw === "asc" || directionRaw === "desc" ? directionRaw : "asc",
    selected: first(searchParams.selected) || null,
    state: first(searchParams.state),
    previewError: first(searchParams.previewError) === "1",
    previewLoading: first(searchParams.previewLoading) === "1",
    previewEmpty: first(searchParams.previewEmpty) === "1",
  };
}

export function permissionsQueryToSearchParams(query: PermissionsQuery, overrides?: Partial<PermissionsQuery>): string {
  const merged = { ...query, ...overrides };
  const params = new URLSearchParams();

  if (merged.search) params.set("search", merged.search);
  if (merged.domain !== "all") params.set("domain", merged.domain);
  if (merged.action !== "all") params.set("action", merged.action);
  if (merged.risk !== "all") params.set("risk", merged.risk);
  if (merged.effect !== "all") params.set("effect", merged.effect);
  if (merged.scope !== "all") params.set("scope", merged.scope);
  if (merged.prerequisite !== "all") params.set("prerequisite", merged.prerequisite);
  if (merged.assignedState !== "all") params.set("assignedState", merged.assignedState);
  if (merged.validationState !== "all") params.set("validationState", merged.validationState);
  if (merged.page > 1) params.set("page", String(merged.page));
  if (merged.pageSize !== DEFAULT_PAGE_SIZE) params.set("pageSize", String(merged.pageSize));
  if (merged.sort !== "key") params.set("sort", merged.sort);
  if (merged.direction !== "asc") params.set("direction", merged.direction);
  if (merged.selected) params.set("selected", merged.selected);
  if (merged.state) params.set("state", merged.state);
  if (merged.previewError) params.set("previewError", "1");
  if (merged.previewLoading) params.set("previewLoading", "1");
  if (merged.previewEmpty) params.set("previewEmpty", "1");

  const s = params.toString();
  return s ? `?${s}` : "";
}

export function defaultPermissionsQuery(): PermissionsQuery {
  return parsePermissionsQuery({});
}
