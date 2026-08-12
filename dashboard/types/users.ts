import type {
  AccessValidationIssue,
  EffectiveAccessSummary,
  MfaState,
  SecurityState,
  User,
  UserRoleAssignment,
  UserStatus,
  UserType,
  UserVerificationState,
  ValidationState,
} from "@/types/access-control";

export type UserSortField =
  | "fullName"
  | "email"
  | "department"
  | "userType"
  | "status"
  | "lastSignIn"
  | "createdAt"
  | "validationState";

export type SortDirection = "asc" | "desc";

export type UsersQuery = {
  search: string;
  status: UserStatus | "all";
  userType: UserType | "all";
  department: string;
  agency: string;
  role: string;
  mfa: MfaState | "all";
  verification: UserVerificationState | "all";
  securityState: SecurityState | "all";
  validationState: ValidationState | "all";
  page: number;
  pageSize: number;
  sort: UserSortField;
  direction: SortDirection;
  selected: string | null;
  directoryScope: "users" | "staff";
  state: string;
  previewError: boolean;
  previewLoading: boolean;
  previewEmpty: boolean;
};

export type UsersSummaryMetrics = {
  totalUsers: number;
  activeUsers: number;
  invitedUsers: number;
  lockedUsers: number;
  suspendedUsers: number;
  mfaEnabledUsers: number;
  usersWithoutRoles: number;
  usersRequiringReview: number;
};

export type UserTableRow = {
  id: string;
  fullName: string;
  displayName: string;
  email: string;
  department: string;
  agencyName?: string;
  orgLabel?: string;
  jobTitle: string;
  userType: UserType;
  userTypeLabel: string;
  assignedRoleNames: string[];
  status: UserStatus;
  mfaState: MfaState;
  lastSignInAt: string | null;
  activeSessionCount: number;
  validationState: ValidationState;
};

export type UsersPageResult = {
  users: UserTableRow[];
  total: number;
  page: number;
  pageSize: number;
  pageCount: number;
  summary: UsersSummaryMetrics;
  facets: {
    departments: string[];
    roles: { id: string; name: string }[];
    statuses: UserStatus[];
    userTypes: UserType[];
  };
};

export type UsersModuleResult = {
  state: "ready" | "loading" | "empty" | "error";
  query: UsersQuery;
  summary: UsersSummaryMetrics;
  table: {
    rows: UserTableRow[];
    total: number;
    page: number;
    pageSize: number;
    pageCount: number;
  };
  facets: UsersPageResult["facets"];
  selectedUser: User | null;
  validationSummary: {
    valid: number;
    warning: number;
    blocked: number;
    review: number;
  };
};

export type UserDetailView = {
  user: User;
  assignedRoles: UserRoleAssignment[];
  effectiveAccess: EffectiveAccessSummary;
  validationIssues: AccessValidationIssue[];
};

export type UsersModuleKey = "directory" | "roles" | "permissions";

export type UsersShellSummary = {
  userCount: number;
  roleCount: number;
  permissionCount: number;
  highRiskPermissionCount: number;
};
