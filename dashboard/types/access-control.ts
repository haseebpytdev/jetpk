import { CMS_BRAND_ID, CMS_BRAND_LABEL } from "@/lib/reports/constants";

/** Stable brand identifier for access-control fixtures. */
export type AccessBrand = typeof CMS_BRAND_ID;

export const ACCESS_BRAND: { id: AccessBrand; label: typeof CMS_BRAND_LABEL } = {
  id: CMS_BRAND_ID,
  label: CMS_BRAND_LABEL,
};

export type UserId = string;
export type RoleId = string;
export type PermissionId = string;

export type UserStatus =
  | "active"
  | "invited"
  | "pendingVerification"
  | "suspended"
  | "locked"
  | "disabled"
  | "archived";

export type UserType =
  | "superAdministrator"
  | "administrator"
  | "operationsManager"
  | "bookingAgent"
  | "agentStaff"
  | "customer"
  | "ticketingAgent"
  | "financeOfficer"
  | "customerSupport"
  | "contentManager"
  | "analyst"
  | "readOnlyAuditor";

export type UserVerificationState = "verified" | "pending" | "unverified" | "expired";

export type MfaState = "enabled" | "disabled" | "required" | "pendingSetup";

export type InvitationState = "none" | "pending" | "accepted" | "expired" | "revoked";

export type SecurityState =
  | "normal"
  | "warning"
  | "locked"
  | "suspended"
  | "reviewRequired"
  | "staleInvitation";

export type ValidationState = "valid" | "warning" | "blocked" | "review";

export type UserContact = {
  email: string;
  phone: string | null;
  phoneExtension: string | null;
};

export type UserProfile = {
  fullName: string;
  displayName: string;
  department: string;
  jobTitle: string;
  userType: UserType;
};

export type UserSecurityState = {
  status: UserStatus;
  verificationState: UserVerificationState;
  mfaState: MfaState;
  invitationState: InvitationState;
  securityState: SecurityState;
  failedSignInCount: number;
  activeSessionCount: number;
  lastSignInAt: string | null;
  mfaRequired: boolean;
};

export type UserActivitySummary = {
  recentActions: string[];
  lastViewedModule: string | null;
  signInCount30d: number;
  recordViews30d: number;
};

export type UserSessionSummary = {
  activeSessionCount: number;
  lastSignInAt: string | null;
  lastSignInMaskedLocation: string | null;
};

export type UserRoleAssignment = {
  roleId: RoleId;
  assignedAt: string;
  assignedBy: string;
  source: "system" | "manual" | "preview";
};

export type RoleStatus = "active" | "inactive" | "deprecated" | "draft";

export type RoleCategory =
  | "system"
  | "operations"
  | "finance"
  | "content"
  | "analytics"
  | "audit"
  | "custom";

export type RoleScope =
  | "allChannels"
  | "gdsOnly"
  | "ndcOnly"
  | "specificSupplier"
  | "assignedBranch"
  | "ownRecords"
  | "allRecords";

export type Role = {
  id: RoleId;
  key: string;
  name: string;
  description: string;
  category: RoleCategory;
  status: RoleStatus;
  isSystem: boolean;
  isProtected: boolean;
  assignedUserCount: number;
  permissionCount: number;
  permissionGroups: PermissionGroup[];
  scope: RoleScope;
  createdAt: string;
  updatedAt: string;
  revision: number;
  lastEditor: string;
  validationState: ValidationState;
};

export type PermissionGroup =
  | "dashboard"
  | "bookings"
  | "payments"
  | "customers"
  | "suppliers"
  | "agents"
  | "pnrs"
  | "tickets"
  | "reports"
  | "cms"
  | "users"
  | "roles"
  | "settings"
  | "audit";

export type PermissionScope =
  | "all"
  | "own"
  | "branch"
  | "supplier"
  | "channel:gds"
  | "channel:ndc"
  | "channel:oneApi"
  | "channel:manual"
  | "channel:mock";

export type ActionType = "view" | "create" | "update" | "request" | "approve" | "manage" | "export" | "invite" | "suspend" | "assign";

export type ResourceType =
  | "dashboard"
  | "booking"
  | "payment"
  | "customer"
  | "supplier"
  | "agent"
  | "pnr"
  | "ticket"
  | "report"
  | "cms"
  | "user"
  | "role"
  | "setting"
  | "audit";

export type PermissionEffect = "allow" | "deny";

export type PermissionRisk = "standard" | "elevated" | "high";

export type Permission = {
  id: PermissionId;
  key: string;
  domain: PermissionGroup;
  action: ActionType;
  label: string;
  description: string;
  risk: PermissionRisk;
  isHighRisk: boolean;
  prerequisiteKey: string | null;
  supportedScopes: PermissionScope[];
  channelAware: boolean;
  laravelPolicyHint: string;
  implementationStatus: "fixture" | "planned" | "partial";
};

export type RolePermission = {
  roleId: RoleId;
  permissionId: PermissionId;
  permissionKey: string;
  effect: PermissionEffect;
  scope: PermissionScope;
};

export type AccessDecisionReason =
  | "granted"
  | "denied_no_role"
  | "denied_no_permission"
  | "denied_scope"
  | "denied_channel"
  | "requires_approval"
  | "unavailable_preview";

export type AccessDecision = {
  allowed: boolean;
  denied: boolean;
  requiresApproval: boolean;
  unavailable: boolean;
  reason: AccessDecisionReason;
  sourceRoleId: RoleId | null;
  scope: PermissionScope | null;
  highRisk: boolean;
  permissionKey: string;
};

export type PolicyDefinition = {
  key: string;
  resourceType: ResourceType;
  action: ActionType;
  permissionKey: string;
  requiresApproval: boolean;
  channelScoped: boolean;
};

export type PolicyEvaluationResult = {
  policy: PolicyDefinition;
  decision: AccessDecision;
};

export type AccessValidationSeverity = "error" | "warning" | "info";

export type AccessValidationIssue = {
  severity: AccessValidationSeverity;
  code: string;
  message: string;
  fieldPath: string;
  entityId: string;
  suggestedResolution: string;
  blocking: boolean;
};

export type AccessValidationResult = {
  valid: boolean;
  issues: AccessValidationIssue[];
};

export type EffectiveAccessDomainSummary = {
  domain: PermissionGroup;
  label: string;
  permissionCount: number;
  viewAccess: boolean;
  requestAccess: boolean;
  approvalAccess: boolean;
  manageAccess: boolean;
  exportAccess: boolean;
  highRiskCount: number;
};

export type EffectiveAccessSummary = {
  domains: EffectiveAccessDomainSummary[];
  totalPermissions: number;
  highRiskPermissions: string[];
  roleIds: RoleId[];
};

export type User = {
  id: UserId;
  profile: UserProfile;
  contact: UserContact;
  assignedRoles: UserRoleAssignment[];
  effectiveAccess: EffectiveAccessSummary;
  security: UserSecurityState;
  activity: UserActivitySummary;
  session: UserSessionSummary;
  validationState: ValidationState;
  validationIssues: AccessValidationIssue[];
  createdAt: string;
  updatedAt: string;
  createdBy: string;
  updatedBy: string;
  notes: string | null;
};

export type AuditSeverity = "info" | "notice" | "warning" | "critical";

export type AuditOutcome = "success" | "failure" | "partial" | "preview";

export type AuditActorType =
  | "dashboardUser"
  | "system"
  | "supplierChannel"
  | "anonymousPreview"
  | "scheduledProcess";

export type AuditTargetType =
  | "user"
  | "role"
  | "permission"
  | "booking"
  | "payment"
  | "customer"
  | "supplier"
  | "agent"
  | "pnrOrder"
  | "ticketDocument"
  | "report"
  | "cmsPage"
  | "cmsSection"
  | "setting"
  | "integration"
  | "auditEvent"
  | "dashboard";

export type AuditCategory =
  | "authentication"
  | "users"
  | "roles"
  | "permissions"
  | "bookings"
  | "operations"
  | "reports"
  | "cms"
  | "settings"
  | "security";

export type AuditRiskState = "none" | "low" | "elevated" | "high" | "critical";

export type AuditAuthorizationOutcome =
  | "allowed"
  | "denied"
  | "requiresApproval"
  | "unavailable"
  | "notApplicable";

export type AuditChannel = "gds" | "ndc" | "oneApi" | "manual" | "mock" | "dashboard" | null;

export type AuditRetentionCategory = "standard" | "security" | "compliance" | "operational";

export type AuditEventType =
  | "auth.signedIn"
  | "auth.signInFailed"
  | "auth.accountLockWarning"
  | "auth.mfaChallenge"
  | "auth.invitationViewed"
  | "auth.invitationExpired"
  | "auth.sessionPolicyWarning"
  | "user.recordViewed"
  | "user.rolePreviewOpened"
  | "user.rolePreviewApplied"
  | "user.securityReviewOpened"
  | "role.viewed"
  | "permission.catalogueViewed"
  | "role.comparisonOpened"
  | "permission.assignmentPreviewOpened"
  | "permission.authorizationPreviewEvaluated"
  | "permission.matrixViewed"
  | "booking.viewed"
  | "payment.viewed"
  | "pnr.viewed"
  | "ticket.viewed"
  | "cancellation.eligibilityViewed"
  | "ticketing.readinessViewed"
  | "report.viewed"
  | "report.filterChanged"
  | "report.exportPreviewGenerated"
  | "cms.pagePreviewViewed"
  | "cms.sectionPreviewChanged"
  | "cms.validationWarningViewed"
  | "cms.bannerPreviewViewed"
  | "settings.viewed"
  | "settings.generalPreviewChanged"
  | "settings.securityPreviewViewed"
  | "settings.notificationPreviewChanged"
  | "settings.integrationMetadataViewed"
  | "security.highRiskPermissionDetected"
  | "security.protectedRoleReview"
  | "security.excessiveAccessWarning"
  | "security.unsafeLinkValidation"
  | "security.warningDetected";

export type AuditActor = {
  actorType: AuditActorType;
  userId: UserId | null;
  displayName: string;
  roleLabel: string | null;
  department: string | null;
  status: UserStatus | null;
  highRiskAccess: boolean;
};

export type AuditTarget = {
  type: AuditTargetType;
  id: string;
  label: string;
};

export type AuditMetadata = {
  maskedIp: string | null;
  maskedNetworkRange: string | null;
  userAgentSummary: string | null;
  channel: AuditChannel;
  scope: string | null;
  module: string;
  route: string | null;
  previewOnly: boolean;
  correlationId: string | null;
  referenceLabel: string | null;
  retentionCategory: AuditRetentionCategory;
  syntheticSource: "fixture";
};

export type AuditEvent = {
  id: string;
  type: AuditEventType;
  category: AuditCategory;
  severity: AuditSeverity;
  outcome: AuditOutcome;
  actor: AuditActor;
  target: AuditTarget;
  actionLabel: string;
  summary: string;
  occurredAt: string;
  sourceModule: string;
  permissionKey: string | null;
  authorizationOutcome: AuditAuthorizationOutcome;
  roleContext: string | null;
  riskState: AuditRiskState;
  changeSummary: string | null;
  validationState: ValidationState;
  metadata: AuditMetadata;
};

export type SettingsSection =
  | "general"
  | "security"
  | "notifications"
  | "integrations";

export type SettingsFieldType =
  | "text"
  | "email"
  | "phone"
  | "select"
  | "boolean"
  | "number"
  | "duration"
  | "metadata";

export type SettingsField = {
  key: string;
  section: SettingsSection;
  label: string;
  description: string;
  type: SettingsFieldType;
  value: SettingsValue;
  readOnly: boolean;
  sensitive: boolean;
};

export type SettingsValue = string | number | boolean | null;

export type SettingsValidationIssue = AccessValidationIssue;

export type SettingsCategoryDefinition = {
  section: SettingsSection;
  label: string;
  description: string;
  fields: SettingsField[];
};

export const USER_TYPE_LABELS: Record<UserType, string> = {
  superAdministrator: "Platform Admin",
  administrator: "Administrator",
  operationsManager: "Staff",
  bookingAgent: "Agent",
  agentStaff: "Agent Staff",
  customer: "Customer",
  ticketingAgent: "Ticketing Agent",
  financeOfficer: "Finance Officer",
  customerSupport: "Customer Support",
  contentManager: "Content Manager",
  analyst: "Analyst",
  readOnlyAuditor: "Read-only Auditor",
};

export const USER_STATUS_LABELS: Record<UserStatus, string> = {
  active: "Active",
  invited: "Invited",
  pendingVerification: "Pending verification",
  suspended: "Suspended",
  locked: "Locked",
  disabled: "Disabled",
  archived: "Archived",
};
