import { getRoleName } from "@/lib/access-control/effective-access";
import { USER_TYPE_LABELS } from "@/types/access-control";
import type { User } from "@/types/access-control";
import type {
  UserSortField,
  UsersPageResult,
  UsersQuery,
  UsersSummaryMetrics,
  UserTableRow,
} from "@/types/users";

export function countActiveUserFilters(query: UsersQuery): number {
  let count = 0;
  if (query.search) count += 1;
  if (query.status !== "all") count += 1;
  if (query.userType !== "all") count += 1;
  if (query.department) count += 1;
  if (query.role) count += 1;
  if (query.mfa !== "all") count += 1;
  if (query.verification !== "all") count += 1;
  if (query.securityState !== "all") count += 1;
  if (query.validationState !== "all") count += 1;
  return count;
}

function matchesSearch(user: User, search: string): boolean {
  if (!search) return true;
  const q = search.toLowerCase();
  return (
    user.id.toLowerCase().includes(q) ||
    user.profile.fullName.toLowerCase().includes(q) ||
    user.profile.displayName.toLowerCase().includes(q) ||
    user.contact.email.toLowerCase().includes(q) ||
    user.profile.department.toLowerCase().includes(q) ||
    user.profile.jobTitle.toLowerCase().includes(q)
  );
}

function filterUsers(users: User[], query: UsersQuery): User[] {
  return users.filter((user) => {
    if (!matchesSearch(user, query.search)) return false;
    if (query.status !== "all" && user.security.status !== query.status) return false;
    if (query.userType !== "all" && user.profile.userType !== query.userType) return false;
    if (query.department && user.profile.department !== query.department) return false;
    if (query.role && !user.assignedRoles.some((r) => r.roleId === query.role)) return false;
    if (query.mfa !== "all" && user.security.mfaState !== query.mfa) return false;
    if (query.verification !== "all" && user.security.verificationState !== query.verification) return false;
    if (query.securityState !== "all" && user.security.securityState !== query.securityState) return false;
    if (query.validationState !== "all" && user.validationState !== query.validationState) return false;
    return true;
  });
}

function statusPriority(status: User["security"]["status"]): number {
  const order: Record<User["security"]["status"], number> = {
    locked: 0,
    suspended: 1,
    pendingVerification: 2,
    invited: 3,
    active: 4,
    disabled: 5,
    archived: 6,
  };
  return order[status];
}

function sortUsers(users: User[], sort: UserSortField, direction: "asc" | "desc"): User[] {
  const dir = direction === "asc" ? 1 : -1;
  return [...users].sort((a, b) => {
    let cmp = 0;
    switch (sort) {
      case "fullName":
        cmp = a.profile.fullName.localeCompare(b.profile.fullName);
        break;
      case "email":
        cmp = a.contact.email.localeCompare(b.contact.email);
        break;
      case "department":
        cmp = a.profile.department.localeCompare(b.profile.department);
        break;
      case "userType":
        cmp = a.profile.userType.localeCompare(b.profile.userType);
        break;
      case "status":
        cmp = statusPriority(a.security.status) - statusPriority(b.security.status);
        break;
      case "lastSignIn": {
        const aTime = a.security.lastSignInAt ? new Date(a.security.lastSignInAt).getTime() : 0;
        const bTime = b.security.lastSignInAt ? new Date(b.security.lastSignInAt).getTime() : 0;
        cmp = aTime - bTime;
        break;
      }
      case "createdAt":
        cmp = new Date(a.createdAt).getTime() - new Date(b.createdAt).getTime();
        break;
      case "validationState":
        cmp = a.validationState.localeCompare(b.validationState);
        break;
      default:
        cmp = a.id.localeCompare(b.id);
    }
    if (cmp === 0) cmp = a.id.localeCompare(b.id);
    return cmp * dir;
  });
}

function toTableRow(user: User): UserTableRow {
  return {
    id: user.id,
    fullName: user.profile.fullName,
    displayName: user.profile.displayName,
    email: user.contact.email,
    department: user.profile.department || "—",
    jobTitle: user.profile.jobTitle,
    userType: user.profile.userType,
    userTypeLabel: USER_TYPE_LABELS[user.profile.userType],
    assignedRoleNames: user.assignedRoles.map((r) => getRoleName(r.roleId)),
    status: user.security.status,
    mfaState: user.security.mfaState,
    lastSignInAt: user.security.lastSignInAt,
    activeSessionCount: user.session.activeSessionCount,
    validationState: user.validationState,
  };
}

export function buildUsersSummary(users: User[]): UsersSummaryMetrics {
  return {
    totalUsers: users.length,
    activeUsers: users.filter((u) => u.security.status === "active").length,
    invitedUsers: users.filter((u) => u.security.status === "invited").length,
    lockedUsers: users.filter((u) => u.security.status === "locked").length,
    suspendedUsers: users.filter((u) => u.security.status === "suspended").length,
    mfaEnabledUsers: users.filter((u) => u.security.mfaState === "enabled").length,
    usersWithoutRoles: users.filter((u) => u.assignedRoles.length === 0).length,
    usersRequiringReview: users.filter(
      (u) => u.validationState === "review" || u.validationState === "warning" || u.validationState === "blocked",
    ).length,
  };
}

export function buildUsersPage(query: UsersQuery, allUsers: User[], roles: { id: string; name: string }[]): UsersPageResult {
  const filtered = sortUsers(filterUsers(allUsers, query), query.sort, query.direction);
  const total = filtered.length;
  const pageCount = Math.max(1, Math.ceil(total / query.pageSize));
  const page = Math.min(query.page, pageCount);
  const start = (page - 1) * query.pageSize;
  const pageUsers = filtered.slice(start, start + query.pageSize);

  const departments = [...new Set(allUsers.map((u) => u.profile.department).filter(Boolean))].sort();

  return {
    users: pageUsers.map(toTableRow),
    total,
    page,
    pageSize: query.pageSize,
    pageCount,
    summary: buildUsersSummary(allUsers),
    facets: {
      departments,
      roles,
      statuses: ["active", "invited", "pendingVerification", "suspended", "locked", "disabled", "archived"],
      userTypes: [
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
      ],
    },
  };
}
