import type {
  MfaState,
  SecurityState,
  UserStatus,
  UserType,
  UserVerificationState,
  ValidationState,
} from "@/types/access-control";
import type { SortDirection, UserSortField, UsersQuery } from "@/types/users";

const DEFAULT_PAGE_SIZE = 20;

const USER_STATUSES: UserStatus[] = [
  "active",
  "invited",
  "pendingVerification",
  "suspended",
  "locked",
  "disabled",
  "archived",
];

const USER_TYPES: UserType[] = [
  "superAdministrator",
  "administrator",
  "operationsManager",
  "bookingAgent",
  "ticketingAgent",
  "financeOfficer",
  "customerSupport",
  "contentManager",
  "analyst",
  "readOnlyAuditor",
];

const MFA_STATES: MfaState[] = ["enabled", "disabled", "required", "pendingSetup"];
const VERIFICATION_STATES: UserVerificationState[] = ["verified", "pending", "unverified", "expired"];
const SECURITY_STATES: SecurityState[] = [
  "normal",
  "warning",
  "locked",
  "suspended",
  "reviewRequired",
  "staleInvitation",
];
const VALIDATION_STATES: ValidationState[] = ["valid", "warning", "blocked", "review"];

const SORT_FIELDS: UserSortField[] = [
  "fullName",
  "email",
  "department",
  "userType",
  "status",
  "lastSignIn",
  "createdAt",
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

export function parseUsersQuery(
  searchParams: Record<string, string | string[] | undefined>,
): UsersQuery {
  const sortRaw = first(searchParams.sort);
  const directionRaw = first(searchParams.direction);

  return {
    search: first(searchParams.search).trim() || first(searchParams.q).trim(),
    status: parseEnum(first(searchParams.status), USER_STATUSES, "all"),
    userType: parseEnum(first(searchParams.userType), USER_TYPES, "all"),
    department: first(searchParams.department),
    role: first(searchParams.role),
    mfa: parseEnum(first(searchParams.mfa), MFA_STATES, "all"),
    verification: parseEnum(first(searchParams.verification), VERIFICATION_STATES, "all"),
    securityState: parseEnum(first(searchParams.securityState), SECURITY_STATES, "all"),
    validationState: parseEnum(first(searchParams.validationState), VALIDATION_STATES, "all"),
    page: parsePositiveInt(first(searchParams.page), 1),
    pageSize: parsePageSize(first(searchParams.pageSize)),
    sort: SORT_FIELDS.includes(sortRaw as UserSortField) ? (sortRaw as UserSortField) : "fullName",
    direction: directionRaw === "asc" || directionRaw === "desc" ? directionRaw : "asc",
    selected: first(searchParams.selected) || null,
    state: first(searchParams.state),
    previewError: first(searchParams.previewError) === "1",
    previewLoading: first(searchParams.previewLoading) === "1",
    previewEmpty: first(searchParams.previewEmpty) === "1",
  };
}

export function usersQueryToSearchParams(query: UsersQuery, overrides?: Partial<UsersQuery>): string {
  const merged = { ...query, ...overrides };
  const params = new URLSearchParams();

  if (merged.search) params.set("search", merged.search);
  if (merged.status !== "all") params.set("status", merged.status);
  if (merged.userType !== "all") params.set("userType", merged.userType);
  if (merged.department) params.set("department", merged.department);
  if (merged.role) params.set("role", merged.role);
  if (merged.mfa !== "all") params.set("mfa", merged.mfa);
  if (merged.verification !== "all") params.set("verification", merged.verification);
  if (merged.securityState !== "all") params.set("securityState", merged.securityState);
  if (merged.validationState !== "all") params.set("validationState", merged.validationState);
  if (merged.page > 1) params.set("page", String(merged.page));
  if (merged.pageSize !== DEFAULT_PAGE_SIZE) params.set("pageSize", String(merged.pageSize));
  if (merged.sort !== "fullName") params.set("sort", merged.sort);
  if (merged.direction !== "asc") params.set("direction", merged.direction);
  if (merged.selected) params.set("selected", merged.selected);
  if (merged.state) params.set("state", merged.state);
  if (merged.previewError) params.set("previewError", "1");
  if (merged.previewLoading) params.set("previewLoading", "1");
  if (merged.previewEmpty) params.set("previewEmpty", "1");

  const s = params.toString();
  return s ? `?${s}` : "";
}

export function defaultUsersQuery(): UsersQuery {
  return parseUsersQuery({});
}

export type { SortDirection };
