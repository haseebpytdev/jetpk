import type {
  AuditActor,
  AuditActorType,
  AuditAuthorizationOutcome,
  AuditCategory,
  AuditChannel,
  AuditEvent,
  AuditEventType,
  AuditMetadata,
  AuditOutcome,
  AuditRetentionCategory,
  AuditRiskState,
  AuditSeverity,
  AuditTarget,
  AuditTargetType,
  UserStatus,
  ValidationState,
} from "@/types/access-control";

/** Fixed audit window anchor — end of fixture month so presets include June fixture events. */
export const AUDIT_REFERENCE_DATE = "2026-06-30T12:00:00.000Z";

type ActorSeed = {
  actorType?: AuditActorType;
  userId: string | null;
  displayName: string;
  roleLabel: string | null;
  department: string | null;
  status: UserStatus | null;
  highRiskAccess?: boolean;
};

type EventSeed = {
  id: string;
  type: AuditEventType;
  category: AuditCategory;
  severity: AuditSeverity;
  outcome: AuditOutcome;
  actor: ActorSeed;
  target: { type: AuditTargetType; id: string; label: string };
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
  metadata: {
    maskedIp: string | null;
    maskedNetworkRange: string | null;
    userAgentSummary: string | null;
    channel: AuditChannel;
    scope: string | null;
    module: string;
    route: string | null;
    correlationId: string | null;
    referenceLabel: string | null;
    retentionCategory: AuditRetentionCategory;
  };
};

function buildActor(seed: ActorSeed): AuditActor {
  return {
    actorType: seed.actorType ?? (seed.userId ? "dashboardUser" : "system"),
    userId: seed.userId,
    displayName: seed.displayName,
    roleLabel: seed.roleLabel,
    department: seed.department,
    status: seed.status,
    highRiskAccess: seed.highRiskAccess ?? false,
  };
}

function buildMetadata(seed: EventSeed["metadata"]): AuditMetadata {
  return {
    maskedIp: seed.maskedIp,
    maskedNetworkRange: seed.maskedNetworkRange,
    userAgentSummary: seed.userAgentSummary,
    channel: seed.channel,
    scope: seed.scope,
    module: seed.module,
    route: seed.route,
    previewOnly: true,
    correlationId: seed.correlationId,
    referenceLabel: seed.referenceLabel,
    retentionCategory: seed.retentionCategory,
    syntheticSource: "fixture",
  };
}

function buildEvent(seed: EventSeed): AuditEvent {
  return {
    id: seed.id,
    type: seed.type,
    category: seed.category,
    severity: seed.severity,
    outcome: seed.outcome,
    actor: buildActor(seed.actor),
    target: seed.target,
    actionLabel: seed.actionLabel,
    summary: seed.summary,
    occurredAt: seed.occurredAt,
    sourceModule: seed.sourceModule,
    permissionKey: seed.permissionKey,
    authorizationOutcome: seed.authorizationOutcome,
    roleContext: seed.roleContext,
    riskState: seed.riskState,
    changeSummary: seed.changeSummary,
    validationState: seed.validationState,
    metadata: buildMetadata(seed.metadata),
  };
}

const U = {
  u01: { userId: "JP-USR-0001", displayName: "Ayesha K.", roleLabel: "Super Administrator", department: "Executive", status: "active" as const },
  u02: { userId: "JP-USR-0002", displayName: "Bilal A.", roleLabel: "Operations Manager", department: "Operations", status: "active" as const },
  u03: { userId: "JP-USR-0003", displayName: "Sana M.", roleLabel: "Booking Agent", department: "Operations", status: "active" as const },
  u04: { userId: "JP-USR-0004", displayName: "Hassan R.", roleLabel: "Ticketing Agent", department: "Operations", status: "active" as const },
  u05: { userId: "JP-USR-0005", displayName: "Fatima N.", roleLabel: "Finance Officer", department: "Finance", status: "active" as const },
  u06: { userId: "JP-USR-0006", displayName: "Omar S.", roleLabel: "Customer Support", department: "Customer Experience", status: "active" as const },
  u07: { userId: "JP-USR-0007", displayName: "Zainab A.", roleLabel: "Content Manager", department: "Content", status: "active" as const },
  u08: { userId: "JP-USR-0008", displayName: "Usman T.", roleLabel: "Analyst", department: "Analytics", status: "active" as const },
  u09: { userId: "JP-USR-0009", displayName: "Nadia H.", roleLabel: "Read-only Auditor", department: "Compliance", status: "active" as const },
  u10: { userId: "JP-USR-0010", displayName: "Kamran I.", roleLabel: "Administrator", department: "Technology", status: "active" as const },
  u11: { userId: "JP-USR-0011", displayName: "Rabia S.", roleLabel: "Booking Agent", department: "Operations", status: "invited" as const },
  u12: { userId: "JP-USR-0012", displayName: "Imran Q.", roleLabel: null, department: "Operations", status: "active" as const },
  u13: { userId: "JP-USR-0013", displayName: "Mehwish A.", roleLabel: "Operations Manager", department: "Operations", status: "active" as const },
  u14: { userId: "JP-USR-0014", displayName: "Tariq M.", roleLabel: "Finance Officer", department: "Finance", status: "active" as const },
  u15: { userId: "JP-USR-0015", displayName: "Sadia F.", roleLabel: "Customer Support", department: "Customer Experience", status: "suspended" as const },
  u16: { userId: "JP-USR-0016", displayName: "Waqas B.", roleLabel: "Booking Agent", department: "Operations", status: "locked" as const },
  u17: { userId: "JP-USR-0017", displayName: "Hina A.", roleLabel: "Content Manager", department: "Content", status: "pendingVerification" as const },
  u18: { userId: "JP-USR-0018", displayName: "Asad J.", roleLabel: "Administrator", department: "Technology", status: "active" as const },
  u19: { userId: "JP-USR-0019", displayName: "Maria J.", roleLabel: "Compliance Analyst", department: "Compliance", status: "active" as const },
  u20: { userId: "JP-USR-0020", displayName: "Faisal H.", roleLabel: "Booking Agent", department: "Operations", status: "disabled" as const },
};

const systemActor: ActorSeed = {
  actorType: "scheduledProcess",
  userId: null,
  displayName: "Scheduled preview process",
  roleLabel: null,
  department: null,
  status: null,
};

const meta = (
  ip: string,
  module: string,
  opts: Partial<EventSeed["metadata"]> = {},
): EventSeed["metadata"] => ({
  maskedIp: ip,
  maskedNetworkRange: `${ip.split(".").slice(0, 3).join(".")}.0/24 (TEST-NET)`,
  userAgentSummary: opts.userAgentSummary ?? "Chrome 126 · Windows 11 · dashboard preview",
  channel: opts.channel ?? null,
  scope: opts.scope ?? null,
  module,
  route: opts.route ?? null,
  correlationId: opts.correlationId ?? null,
  referenceLabel: opts.referenceLabel ?? null,
  retentionCategory: opts.retentionCategory ?? "standard",
});

const EVENT_SEEDS: EventSeed[] = [
  // authentication (8)
  {
    id: "JP-AUD-0001", type: "auth.signedIn", category: "authentication", severity: "info", outcome: "preview",
    actor: U.u01, target: { type: "dashboard", id: "dashboard", label: "Dashboard overview" },
    actionLabel: "Signed in (preview)", summary: "Preview-only sign-in recorded for dashboard session.",
    occurredAt: "2026-06-30T14:22:00.000Z", sourceModule: "authentication", permissionKey: "dashboard.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0001", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("192.0.2.10", "authentication", { route: "/auth/preview", correlationId: "corr-auth-001", retentionCategory: "security" }),
  },
  {
    id: "JP-AUD-0002", type: "auth.signInFailed", category: "authentication", severity: "warning", outcome: "failure",
    actor: U.u16, target: { type: "user", id: "JP-USR-0016", label: "Waqas B." },
    actionLabel: "Sign-in failed (preview)", summary: "Preview-only failed sign-in attempt — invalid credentials fixture.",
    occurredAt: "2026-06-30T05:55:00.000Z", sourceModule: "authentication", permissionKey: null,
    authorizationOutcome: "denied", roleContext: "JP-ROL-0003", riskState: "elevated", changeSummary: "Failed attempt count incremented in preview.", validationState: "warning",
    metadata: meta("198.51.100.5", "authentication", { route: "/auth/preview", correlationId: "corr-auth-002", retentionCategory: "security" }),
  },
  {
    id: "JP-AUD-0003", type: "auth.accountLockWarning", category: "authentication", severity: "critical", outcome: "preview",
    actor: { ...U.u16, highRiskAccess: false }, target: { type: "user", id: "JP-USR-0016", label: "Waqas B." },
    actionLabel: "Account lock warning (preview)", summary: "Preview-only account lock threshold reached after repeated failed sign-ins.",
    occurredAt: "2026-06-30T06:00:00.000Z", sourceModule: "authentication", permissionKey: null,
    authorizationOutcome: "notApplicable", roleContext: "JP-ROL-0003", riskState: "critical", changeSummary: "Account marked locked in preview fixture.", validationState: "blocked",
    metadata: meta("198.51.100.6", "authentication", { correlationId: "corr-auth-003", retentionCategory: "security" }),
  },
  {
    id: "JP-AUD-0004", type: "auth.mfaChallenge", category: "authentication", severity: "notice", outcome: "preview",
    actor: U.u04, target: { type: "user", id: "JP-USR-0004", label: "Hassan R." },
    actionLabel: "MFA challenge (preview)", summary: "Preview-only MFA challenge presented — no live token issued.",
    occurredAt: "2026-06-30T08:40:00.000Z", sourceModule: "authentication", permissionKey: "dashboard.view",
    authorizationOutcome: "requiresApproval", roleContext: "JP-ROL-0004", riskState: "low", changeSummary: null, validationState: "valid",
    metadata: meta("192.0.2.11", "authentication", { correlationId: "corr-auth-004", retentionCategory: "security" }),
  },
  {
    id: "JP-AUD-0005", type: "auth.invitationViewed", category: "authentication", severity: "info", outcome: "preview",
    actor: U.u11, target: { type: "user", id: "JP-USR-0011", label: "Rabia S." },
    actionLabel: "Invitation viewed (preview)", summary: "Preview-only staff invitation link opened locally.",
    occurredAt: "2026-06-28T10:00:00.000Z", sourceModule: "authentication", permissionKey: "users.invite",
    authorizationOutcome: "notApplicable", roleContext: null, riskState: "none", changeSummary: null, validationState: "review",
    metadata: meta("203.0.113.5", "authentication", { route: "/auth/invitation/preview", referenceLabel: "INV-PREVIEW-0011" }),
  },
  {
    id: "JP-AUD-0006", type: "auth.invitationExpired", category: "authentication", severity: "notice", outcome: "preview",
    actor: systemActor, target: { type: "user", id: "JP-USR-0011", label: "Rabia S." },
    actionLabel: "Invitation expired (preview)", summary: "Preview-only invitation expiry notice — no outbound email sent.",
    occurredAt: "2026-06-29T00:00:00.000Z", sourceModule: "authentication", permissionKey: null,
    authorizationOutcome: "notApplicable", roleContext: null, riskState: "low", changeSummary: "Invitation state set to expired in preview.", validationState: "review",
    metadata: { ...meta("203.0.113.6", "authentication"), maskedIp: null, maskedNetworkRange: null, userAgentSummary: null, referenceLabel: "INV-PREVIEW-0011", retentionCategory: "operational" },
  },
  {
    id: "JP-AUD-0007", type: "auth.sessionPolicyWarning", category: "authentication", severity: "warning", outcome: "preview",
    actor: { ...U.u04, highRiskAccess: true }, target: { type: "user", id: "JP-USR-0004", label: "Hassan R." },
    actionLabel: "Session policy warning (preview)", summary: "Preview-only concurrent session policy warning — three active preview sessions detected.",
    occurredAt: "2026-06-30T15:30:00.000Z", sourceModule: "authentication", permissionKey: null,
    authorizationOutcome: "notApplicable", roleContext: "JP-ROL-0004", riskState: "elevated", changeSummary: null, validationState: "warning",
    metadata: meta("192.0.2.12", "authentication", { correlationId: "corr-auth-007", retentionCategory: "security" }),
  },
  {
    id: "JP-AUD-0008", type: "auth.signedIn", category: "authentication", severity: "info", outcome: "preview",
    actor: U.u02, target: { type: "dashboard", id: "dashboard", label: "Operations dashboard" },
    actionLabel: "Signed in (preview)", summary: "Preview-only operations manager sign-in recorded.",
    occurredAt: "2026-06-29T09:15:00.000Z", sourceModule: "authentication", permissionKey: "dashboard.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0002", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("192.0.2.13", "authentication", { correlationId: "corr-auth-008" }),
  },

  // users (7)
  {
    id: "JP-AUD-0009", type: "user.recordViewed", category: "users", severity: "info", outcome: "preview",
    actor: U.u10, target: { type: "user", id: "JP-USR-0003", label: "Sana M." },
    actionLabel: "User record viewed (preview)", summary: "Preview-only user directory record opened locally.",
    occurredAt: "2026-06-30T12:05:00.000Z", sourceModule: "users", permissionKey: "users.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0013", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("192.0.2.20", "users", { route: "/users/JP-USR-0003" }),
  },
  {
    id: "JP-AUD-0010", type: "user.rolePreviewOpened", category: "users", severity: "info", outcome: "preview",
    actor: U.u10, target: { type: "user", id: "JP-USR-0012", label: "Imran Q." },
    actionLabel: "Role preview opened (preview)", summary: "Preview-only role assignment preview panel opened — no roles applied.",
    occurredAt: "2026-06-29T11:30:00.000Z", sourceModule: "users", permissionKey: "users.assignRoles",
    authorizationOutcome: "requiresApproval", roleContext: "JP-ROL-0013", riskState: "elevated", changeSummary: null, validationState: "warning",
    metadata: meta("198.51.100.20", "users", { route: "/users/JP-USR-0012/roles/preview", retentionCategory: "compliance" }),
  },
  {
    id: "JP-AUD-0011", type: "user.rolePreviewApplied", category: "users", severity: "notice", outcome: "preview",
    actor: U.u02, target: { type: "user", id: "JP-USR-0012", label: "Imran Q." },
    actionLabel: "Role preview applied (preview)", summary: "Preview-only role assignment simulation — changes not persisted.",
    occurredAt: "2026-06-29T11:35:00.000Z", sourceModule: "users", permissionKey: "users.assignRoles",
    authorizationOutcome: "requiresApproval", roleContext: "JP-ROL-0002", riskState: "high", changeSummary: "Simulated JP-ROL-0003 assignment in preview.", validationState: "warning",
    metadata: meta("198.51.100.21", "users", { route: "/users/JP-USR-0012/roles/preview", referenceLabel: "ROLE-PREVIEW-0012", retentionCategory: "compliance" }),
  },
  {
    id: "JP-AUD-0012", type: "user.securityReviewOpened", category: "users", severity: "notice", outcome: "preview",
    actor: U.u09, target: { type: "user", id: "JP-USR-0015", label: "Sadia F." },
    actionLabel: "Security review opened (preview)", summary: "Preview-only security review panel opened for suspended user fixture.",
    occurredAt: "2026-06-30T08:10:00.000Z", sourceModule: "users", permissionKey: "users.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0009", riskState: "elevated", changeSummary: null, validationState: "blocked",
    metadata: meta("203.0.113.10", "users", { route: "/users/JP-USR-0015/security", retentionCategory: "security" }),
  },
  {
    id: "JP-AUD-0013", type: "user.recordViewed", category: "users", severity: "info", outcome: "preview",
    actor: U.u02, target: { type: "user", id: "JP-USR-0016", label: "Waqas B." },
    actionLabel: "User record viewed (preview)", summary: "Preview-only locked user record viewed in directory.",
    occurredAt: "2026-06-30T06:30:00.000Z", sourceModule: "users", permissionKey: "users.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0002", riskState: "low", changeSummary: null, validationState: "blocked",
    metadata: meta("192.0.2.21", "users", { route: "/users/JP-USR-0016" }),
  },
  {
    id: "JP-AUD-0014", type: "user.recordViewed", category: "users", severity: "info", outcome: "preview",
    actor: U.u01, target: { type: "user", id: "JP-USR-0020", label: "Faisal H." },
    actionLabel: "User record viewed (preview)", summary: "Preview-only disabled user record viewed for audit review.",
    occurredAt: "2026-06-27T14:00:00.000Z", sourceModule: "users", permissionKey: "users.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0001", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("198.51.100.22", "users", { route: "/users/JP-USR-0020" }),
  },
  {
    id: "JP-AUD-0015", type: "user.rolePreviewOpened", category: "users", severity: "info", outcome: "preview",
    actor: U.u01, target: { type: "user", id: "JP-USR-0019", label: "Maria J." },
    actionLabel: "Role preview opened (preview)", summary: "Preview-only role preview opened for compliance analyst fixture.",
    occurredAt: "2026-06-29T08:15:00.000Z", sourceModule: "users", permissionKey: "users.assignRoles",
    authorizationOutcome: "requiresApproval", roleContext: "JP-ROL-0001", riskState: "elevated", changeSummary: null, validationState: "warning",
    metadata: meta("203.0.113.11", "users", { route: "/users/JP-USR-0019/roles/preview", retentionCategory: "compliance" }),
  },

  // roles (4)
  {
    id: "JP-AUD-0016", type: "role.viewed", category: "roles", severity: "info", outcome: "preview",
    actor: U.u10, target: { type: "role", id: "JP-ROL-0003", label: "Booking Agent" },
    actionLabel: "Role viewed (preview)", summary: "Preview-only role definition viewed in roles workspace.",
    occurredAt: "2026-06-30T12:10:00.000Z", sourceModule: "roles", permissionKey: "roles.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0013", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("192.0.2.30", "roles", { route: "/users/roles/JP-ROL-0003" }),
  },
  {
    id: "JP-AUD-0017", type: "role.comparisonOpened", category: "roles", severity: "info", outcome: "preview",
    actor: U.u09, target: { type: "role", id: "JP-ROL-0005", label: "Finance Officer" },
    actionLabel: "Role comparison opened (preview)", summary: "Preview-only role comparison panel opened — read-only fixture.",
    occurredAt: "2026-06-28T09:00:00.000Z", sourceModule: "roles", permissionKey: "roles.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0009", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("198.51.100.30", "roles", { route: "/users/roles/compare", referenceLabel: "JP-ROL-0005 vs JP-ROL-0008" }),
  },
  {
    id: "JP-AUD-0018", type: "role.viewed", category: "roles", severity: "notice", outcome: "preview",
    actor: U.u18, target: { type: "role", id: "JP-ROL-0001", label: "Super Administrator" },
    actionLabel: "Protected role viewed (preview)", summary: "Preview-only protected system role viewed — no edits permitted.",
    occurredAt: "2026-06-28T14:05:00.000Z", sourceModule: "roles", permissionKey: "roles.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0011", riskState: "elevated", changeSummary: null, validationState: "valid",
    metadata: meta("203.0.113.15", "roles", { route: "/users/roles/JP-ROL-0001", retentionCategory: "security" }),
  },
  {
    id: "JP-AUD-0019", type: "role.viewed", category: "roles", severity: "info", outcome: "preview",
    actor: U.u02, target: { type: "role", id: "JP-ROL-0002", label: "Operations Manager" },
    actionLabel: "Role viewed (preview)", summary: "Preview-only operations manager role definition viewed.",
    occurredAt: "2026-06-26T10:00:00.000Z", sourceModule: "roles", permissionKey: "roles.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0002", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("192.0.2.31", "roles", { route: "/users/roles/JP-ROL-0002" }),
  },

  // permissions (6)
  {
    id: "JP-AUD-0020", type: "permission.catalogueViewed", category: "permissions", severity: "info", outcome: "preview",
    actor: U.u09, target: { type: "permission", id: "catalogue", label: "Permission catalogue" },
    actionLabel: "Catalogue viewed (preview)", summary: "Preview-only permission catalogue browsed locally.",
    occurredAt: "2026-06-29T09:00:00.000Z", sourceModule: "permissions", permissionKey: "roles.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0009", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("203.0.113.20", "permissions", { route: "/users/permissions" }),
  },
  {
    id: "JP-AUD-0021", type: "permission.matrixViewed", category: "permissions", severity: "info", outcome: "preview",
    actor: U.u09, target: { type: "permission", id: "matrix", label: "Role-permission matrix" },
    actionLabel: "Matrix viewed (preview)", summary: "Preview-only role-permission matrix viewed in audit workspace.",
    occurredAt: "2026-06-29T09:05:00.000Z", sourceModule: "permissions", permissionKey: "roles.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0009", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("203.0.113.21", "permissions", { route: "/users/permissions/matrix", scope: "all" }),
  },
  {
    id: "JP-AUD-0022", type: "permission.assignmentPreviewOpened", category: "permissions", severity: "notice", outcome: "preview",
    actor: U.u10, target: { type: "role", id: "JP-ROL-0003", label: "Booking Agent" },
    actionLabel: "Assignment preview opened (preview)", summary: "Preview-only permission assignment preview opened — no grants applied.",
    occurredAt: "2026-06-30T12:15:00.000Z", sourceModule: "permissions", permissionKey: "roles.assignPermissions",
    authorizationOutcome: "requiresApproval", roleContext: "JP-ROL-0013", riskState: "high", changeSummary: null, validationState: "valid",
    metadata: meta("192.0.2.32", "permissions", { route: "/users/roles/JP-ROL-0003/permissions/preview", retentionCategory: "compliance" }),
  },
  {
    id: "JP-AUD-0023", type: "permission.authorizationPreviewEvaluated", category: "permissions", severity: "warning", outcome: "failure",
    actor: U.u03, target: { type: "permission", id: "bookings.cancel.approve", label: "Approve booking cancellation" },
    actionLabel: "Authorization denied (preview)", summary: "Preview-only authorization evaluation denied — insufficient permission scope.",
    occurredAt: "2026-06-30T11:10:00.000Z", sourceModule: "permissions", permissionKey: "bookings.cancel.approve",
    authorizationOutcome: "denied", roleContext: "JP-ROL-0003", riskState: "high", changeSummary: null, validationState: "valid",
    metadata: meta("192.0.2.33", "permissions", { scope: "own", channel: "gds", referenceLabel: "AUTH-PREVIEW-DENY-0023", retentionCategory: "security" }),
  },
  {
    id: "JP-AUD-0024", type: "permission.authorizationPreviewEvaluated", category: "permissions", severity: "info", outcome: "preview",
    actor: U.u02, target: { type: "permission", id: "bookings.view", label: "View bookings" },
    actionLabel: "Authorization allowed (preview)", summary: "Preview-only authorization evaluation granted for booking view.",
    occurredAt: "2026-06-29T10:00:00.000Z", sourceModule: "permissions", permissionKey: "bookings.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0002", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("198.51.100.31", "permissions", { scope: "all", channel: "gds" }),
  },
  {
    id: "JP-AUD-0025", type: "permission.matrixViewed", category: "permissions", severity: "info", outcome: "preview",
    actor: U.u19, target: { type: "permission", id: "matrix", label: "Role-permission matrix" },
    actionLabel: "Matrix viewed (preview)", summary: "Preview-only permission matrix viewed by compliance analyst.",
    occurredAt: "2026-06-29T08:30:00.000Z", sourceModule: "permissions", permissionKey: "roles.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0012", riskState: "none", changeSummary: null, validationState: "warning",
    metadata: meta("203.0.113.22", "permissions", { route: "/users/permissions/matrix", scope: "all" }),
  },

  // bookings (5)
  {
    id: "JP-AUD-0026", type: "booking.viewed", category: "bookings", severity: "info", outcome: "preview",
    actor: U.u03, target: { type: "booking", id: "JP-BK-00001", label: "Booking JP-BK-00001" },
    actionLabel: "Booking viewed (preview)", summary: "Preview-only GDS booking record viewed locally.",
    occurredAt: "2026-06-30T11:00:00.000Z", sourceModule: "bookings", permissionKey: "bookings.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0003", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("192.0.2.40", "bookings", { channel: "gds", scope: "own", route: "/bookings/JP-BK-00001" }),
  },
  {
    id: "JP-AUD-0027", type: "booking.viewed", category: "bookings", severity: "info", outcome: "preview",
    actor: U.u03, target: { type: "booking", id: "JP-BK-00002", label: "Booking JP-BK-00002" },
    actionLabel: "Booking viewed (preview)", summary: "Preview-only NDC booking record viewed locally.",
    occurredAt: "2026-06-29T16:00:00.000Z", sourceModule: "bookings", permissionKey: "bookings.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0003", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("198.51.100.40", "bookings", { channel: "ndc", scope: "own", route: "/bookings/JP-BK-00002" }),
  },
  {
    id: "JP-AUD-0028", type: "booking.viewed", category: "bookings", severity: "info", outcome: "preview",
    actor: U.u02, target: { type: "booking", id: "JP-BK-00003", label: "Booking JP-BK-00003" },
    actionLabel: "Booking viewed (preview)", summary: "Preview-only booking record viewed by operations manager.",
    occurredAt: "2026-06-28T11:00:00.000Z", sourceModule: "bookings", permissionKey: "bookings.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0002", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("203.0.113.30", "bookings", { channel: "manual", scope: "all", route: "/bookings/JP-BK-00003" }),
  },
  {
    id: "JP-AUD-0029", type: "booking.viewed", category: "bookings", severity: "info", outcome: "preview",
    actor: U.u06, target: { type: "booking", id: "JP-BK-00004", label: "Booking JP-BK-00004" },
    actionLabel: "Booking viewed (preview)", summary: "Preview-only support booking lookup — read-only fixture.",
    occurredAt: "2026-06-27T10:00:00.000Z", sourceModule: "bookings", permissionKey: "bookings.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0006", riskState: "none", changeSummary: null, validationState: "warning",
    metadata: meta("192.0.2.41", "bookings", { channel: "gds", scope: "own", route: "/bookings/JP-BK-00004" }),
  },
  {
    id: "JP-AUD-0030", type: "booking.viewed", category: "bookings", severity: "info", outcome: "preview",
    actor: U.u13, target: { type: "booking", id: "JP-BK-00005", label: "Booking JP-BK-00005" },
    actionLabel: "Booking viewed (preview)", summary: "Preview-only PNR reviewer booking record viewed.",
    occurredAt: "2026-06-30T10:30:00.000Z", sourceModule: "bookings", permissionKey: "bookings.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0010", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("198.51.100.41", "bookings", { channel: "oneApi", scope: "all", route: "/bookings/JP-BK-00005" }),
  },

  // operations (8)
  {
    id: "JP-AUD-0031", type: "payment.viewed", category: "operations", severity: "info", outcome: "preview",
    actor: U.u05, target: { type: "payment", id: "JP-PAY-00001", label: "Payment JP-PAY-00001" },
    actionLabel: "Payment viewed (preview)", summary: "Preview-only payment ledger record viewed locally.",
    occurredAt: "2026-06-28T16:30:00.000Z", sourceModule: "payments", permissionKey: "payments.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0005", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("198.51.100.10", "payments", { scope: "all", route: "/payments/JP-PAY-00001" }),
  },
  {
    id: "JP-AUD-0032", type: "pnr.viewed", category: "operations", severity: "info", outcome: "preview",
    actor: U.u04, target: { type: "pnrOrder", id: "JP-PNR-00001", label: "PNR ABC123" },
    actionLabel: "PNR viewed (preview)", summary: "Preview-only GDS PNR record viewed — no supplier call made.",
    occurredAt: "2026-06-30T08:45:00.000Z", sourceModule: "pnrs", permissionKey: "pnrs.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0004", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("192.0.2.50", "pnrs", { channel: "gds", scope: "gdsOnly", route: "/pnrs/JP-PNR-00001" }),
  },
  {
    id: "JP-AUD-0033", type: "ticket.viewed", category: "operations", severity: "info", outcome: "preview",
    actor: U.u04, target: { type: "ticketDocument", id: "JP-TKT-00001", label: "Ticket 176-1234567890" },
    actionLabel: "Ticket viewed (preview)", summary: "Preview-only ticket document viewed locally.",
    occurredAt: "2026-06-30T09:00:00.000Z", sourceModule: "tickets", permissionKey: "tickets.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0004", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("192.0.2.51", "tickets", { channel: "gds", route: "/tickets/JP-TKT-00001" }),
  },
  {
    id: "JP-AUD-0034", type: "cancellation.eligibilityViewed", category: "operations", severity: "notice", outcome: "preview",
    actor: U.u13, target: { type: "booking", id: "JP-BK-00001", label: "Booking JP-BK-00001" },
    actionLabel: "Cancellation eligibility viewed (preview)", summary: "Preview-only cancellation eligibility panel viewed — no request submitted.",
    occurredAt: "2026-06-30T10:45:00.000Z", sourceModule: "bookings", permissionKey: "bookings.cancel.request",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0010", riskState: "low", changeSummary: null, validationState: "valid",
    metadata: meta("203.0.113.31", "bookings", { channel: "gds", route: "/bookings/JP-BK-00001/cancellation/preview" }),
  },
  {
    id: "JP-AUD-0035", type: "ticketing.readinessViewed", category: "operations", severity: "info", outcome: "preview",
    actor: U.u04, target: { type: "pnrOrder", id: "JP-PNR-00002", label: "NDC Order ORD-00002" },
    actionLabel: "Ticketing readiness viewed (preview)", summary: "Preview-only ticketing readiness checklist viewed locally.",
    occurredAt: "2026-06-29T14:00:00.000Z", sourceModule: "tickets", permissionKey: "tickets.review",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0014", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("198.51.100.50", "tickets", { channel: "ndc", route: "/tickets/readiness/JP-PNR-00002" }),
  },
  {
    id: "JP-AUD-0036", type: "payment.viewed", category: "operations", severity: "info", outcome: "preview",
    actor: U.u14, target: { type: "payment", id: "JP-PAY-00002", label: "Payment JP-PAY-00002" },
    actionLabel: "Payment viewed (preview)", summary: "Preview-only reconciliation payment record viewed.",
    occurredAt: "2026-06-29T11:00:00.000Z", sourceModule: "payments", permissionKey: "payments.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0005", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("203.0.113.32", "payments", { scope: "all", route: "/payments/JP-PAY-00002" }),
  },
  {
    id: "JP-AUD-0037", type: "pnr.viewed", category: "operations", severity: "info", outcome: "preview",
    actor: U.u13, target: { type: "pnrOrder", id: "JP-PNR-00003", label: "PNR DEF456" },
    actionLabel: "PNR viewed (preview)", summary: "Preview-only PNR review record opened in operations queue.",
    occurredAt: "2026-06-30T10:35:00.000Z", sourceModule: "pnrs", permissionKey: "pnrs.review",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0010", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("192.0.2.52", "pnrs", { channel: "gds", route: "/pnrs/JP-PNR-00003" }),
  },
  {
    id: "JP-AUD-0038", type: "ticket.viewed", category: "operations", severity: "info", outcome: "preview",
    actor: U.u04, target: { type: "ticketDocument", id: "JP-TKT-00002", label: "Ticket 176-9876543210" },
    actionLabel: "Ticket viewed (preview)", summary: "Preview-only NDC ticket document viewed locally.",
    occurredAt: "2026-06-28T15:00:00.000Z", sourceModule: "tickets", permissionKey: "tickets.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0014", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("198.51.100.51", "tickets", { channel: "ndc", route: "/tickets/JP-TKT-00002" }),
  },

  // reports (6)
  {
    id: "JP-AUD-0039", type: "report.viewed", category: "reports", severity: "info", outcome: "preview",
    actor: U.u08, target: { type: "report", id: "JP-RPT-BK-001", label: "Bookings summary report" },
    actionLabel: "Report viewed (preview)", summary: "Preview-only bookings summary report opened locally.",
    occurredAt: "2026-06-29T15:00:00.000Z", sourceModule: "reports", permissionKey: "reports.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0008", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("203.0.113.40", "reports", { route: "/reports/bookings", scope: "all" }),
  },
  {
    id: "JP-AUD-0040", type: "report.filterChanged", category: "reports", severity: "info", outcome: "preview",
    actor: U.u08, target: { type: "report", id: "JP-RPT-BK-001", label: "Bookings summary report" },
    actionLabel: "Report filter changed (preview)", summary: "Preview-only report date filter adjusted — not persisted.",
    occurredAt: "2026-06-29T15:05:00.000Z", sourceModule: "reports", permissionKey: "reports.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0008", riskState: "none", changeSummary: "Date range set to June 2026 in preview.", validationState: "valid",
    metadata: meta("203.0.113.41", "reports", { route: "/reports/bookings", scope: "all" }),
  },
  {
    id: "JP-AUD-0041", type: "report.exportPreviewGenerated", category: "reports", severity: "notice", outcome: "preview",
    actor: U.u08, target: { type: "report", id: "JP-RPT-EXP-001", label: "Bookings export preview" },
    actionLabel: "Export preview generated (preview)", summary: "Preview-only report export file generated locally — no download sent.",
    occurredAt: "2026-06-29T15:10:00.000Z", sourceModule: "reports", permissionKey: "reports.export",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0008", riskState: "low", changeSummary: "CSV export preview stub created.", validationState: "valid",
    metadata: meta("203.0.113.42", "reports", { route: "/reports/bookings/export/preview", referenceLabel: "EXP-PREVIEW-0041" }),
  },
  {
    id: "JP-AUD-0042", type: "report.viewed", category: "reports", severity: "info", outcome: "preview",
    actor: U.u14, target: { type: "report", id: "JP-RPT-PAY-001", label: "Payments reconciliation report" },
    actionLabel: "Report viewed (preview)", summary: "Preview-only payments reconciliation report viewed.",
    occurredAt: "2026-06-28T17:00:00.000Z", sourceModule: "reports", permissionKey: "reports.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0005", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("192.0.2.60", "reports", { route: "/reports/payments", scope: "all" }),
  },
  {
    id: "JP-AUD-0043", type: "report.filterChanged", category: "reports", severity: "info", outcome: "preview",
    actor: U.u05, target: { type: "report", id: "JP-RPT-PAY-001", label: "Payments reconciliation report" },
    actionLabel: "Report filter changed (preview)", summary: "Preview-only payment channel filter changed in report workspace.",
    occurredAt: "2026-06-28T17:05:00.000Z", sourceModule: "reports", permissionKey: "reports.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0005", riskState: "none", changeSummary: "Channel filter set to mock in preview.", validationState: "valid",
    metadata: meta("198.51.100.60", "reports", { route: "/reports/payments", channel: "mock" }),
  },
  {
    id: "JP-AUD-0044", type: "report.exportPreviewGenerated", category: "reports", severity: "notice", outcome: "preview",
    actor: U.u14, target: { type: "report", id: "JP-RPT-EXP-002", label: "Operations export preview" },
    actionLabel: "Export preview generated (preview)", summary: "Preview-only operations report export stub generated locally.",
    occurredAt: "2026-06-27T12:00:00.000Z", sourceModule: "reports", permissionKey: "reports.export",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0008", riskState: "low", changeSummary: "XLSX export preview stub created.", validationState: "valid",
    metadata: meta("203.0.113.43", "reports", { route: "/reports/operations/export/preview", referenceLabel: "EXP-PREVIEW-0044" }),
  },

  // cms (5)
  {
    id: "JP-AUD-0045", type: "cms.pagePreviewViewed", category: "cms", severity: "info", outcome: "preview",
    actor: U.u07, target: { type: "cmsPage", id: "JP-CMS-PG-001", label: "Homepage" },
    actionLabel: "Page preview viewed (preview)", summary: "Preview-only CMS homepage draft preview opened locally.",
    occurredAt: "2026-06-30T13:00:00.000Z", sourceModule: "cms", permissionKey: "cms.preview",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0007", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("192.0.2.70", "cms", { route: "/cms/pages/JP-CMS-PG-001/preview" }),
  },
  {
    id: "JP-AUD-0046", type: "cms.sectionPreviewChanged", category: "cms", severity: "notice", outcome: "preview",
    actor: U.u07, target: { type: "cmsSection", id: "JP-CMS-SC-001", label: "Homepage hero section" },
    actionLabel: "Section preview changed (preview)", summary: "Preview-only CMS section edit simulated — not published or persisted.",
    occurredAt: "2026-06-30T13:05:00.000Z", sourceModule: "cms", permissionKey: "cms.edit",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0007", riskState: "low", changeSummary: "Hero headline text adjusted in local preview.", validationState: "valid",
    metadata: meta("192.0.2.71", "cms", { route: "/cms/sections/JP-CMS-SC-001/preview" }),
  },
  {
    id: "JP-AUD-0047", type: "cms.validationWarningViewed", category: "cms", severity: "warning", outcome: "preview",
    actor: U.u17, target: { type: "cmsPage", id: "JP-CMS-PG-002", label: "Offers landing page" },
    actionLabel: "Validation warning viewed (preview)", summary: "Preview-only CMS validation warning reviewed — missing alt text fixture.",
    occurredAt: "2026-06-26T11:00:00.000Z", sourceModule: "cms", permissionKey: "cms.review",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0007", riskState: "low", changeSummary: null, validationState: "review",
    metadata: meta("198.51.100.70", "cms", { route: "/cms/pages/JP-CMS-PG-002/validation" }),
  },
  {
    id: "JP-AUD-0048", type: "cms.bannerPreviewViewed", category: "cms", severity: "info", outcome: "preview",
    actor: U.u07, target: { type: "cmsSection", id: "JP-CMS-BN-001", label: "Summer sale banner" },
    actionLabel: "Banner preview viewed (preview)", summary: "Preview-only promotional banner preview viewed locally.",
    occurredAt: "2026-06-29T13:00:00.000Z", sourceModule: "cms", permissionKey: "cms.preview",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0007", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("203.0.113.50", "cms", { route: "/cms/banners/JP-CMS-BN-001/preview" }),
  },
  {
    id: "JP-AUD-0049", type: "cms.pagePreviewViewed", category: "cms", severity: "info", outcome: "preview",
    actor: U.u17, target: { type: "cmsPage", id: "JP-CMS-PG-003", label: "Contact page" },
    actionLabel: "Page preview viewed (preview)", summary: "Preview-only contact page draft preview opened by content editor.",
    occurredAt: "2026-06-25T09:00:00.000Z", sourceModule: "cms", permissionKey: "cms.preview",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0007", riskState: "none", changeSummary: null, validationState: "review",
    metadata: meta("192.0.2.72", "cms", { route: "/cms/pages/JP-CMS-PG-003/preview" }),
  },

  // settings (5)
  {
    id: "JP-AUD-0050", type: "settings.viewed", category: "settings", severity: "info", outcome: "preview",
    actor: U.u10, target: { type: "setting", id: "general", label: "General settings" },
    actionLabel: "Settings viewed (preview)", summary: "Preview-only general settings panel viewed locally.",
    occurredAt: "2026-06-30T12:00:00.000Z", sourceModule: "settings", permissionKey: "settings.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0013", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("198.51.100.80", "settings", { route: "/settings/general" }),
  },
  {
    id: "JP-AUD-0051", type: "settings.generalPreviewChanged", category: "settings", severity: "notice", outcome: "preview",
    actor: U.u10, target: { type: "setting", id: "general", label: "General settings" },
    actionLabel: "General preview changed (preview)", summary: "Preview-only general setting value adjusted — not saved to live config.",
    occurredAt: "2026-06-30T12:02:00.000Z", sourceModule: "settings", permissionKey: "settings.update",
    authorizationOutcome: "requiresApproval", roleContext: "JP-ROL-0013", riskState: "elevated", changeSummary: "Support email preview field edited locally.", validationState: "valid",
    metadata: meta("198.51.100.81", "settings", { route: "/settings/general/preview", retentionCategory: "compliance" }),
  },
  {
    id: "JP-AUD-0052", type: "settings.securityPreviewViewed", category: "settings", severity: "notice", outcome: "preview",
    actor: U.u10, target: { type: "setting", id: "security", label: "Security settings" },
    actionLabel: "Security preview viewed (preview)", summary: "Preview-only security settings panel viewed — no secrets displayed.",
    occurredAt: "2026-06-30T12:03:00.000Z", sourceModule: "settings", permissionKey: "settings.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0013", riskState: "elevated", changeSummary: null, validationState: "valid",
    metadata: meta("198.51.100.82", "settings", { route: "/settings/security", retentionCategory: "security" }),
  },
  {
    id: "JP-AUD-0053", type: "settings.notificationPreviewChanged", category: "settings", severity: "info", outcome: "preview",
    actor: U.u18, target: { type: "setting", id: "notifications", label: "Notification settings" },
    actionLabel: "Notification preview changed (preview)", summary: "Preview-only notification preference toggled locally — no emails sent.",
    occurredAt: "2026-06-28T14:10:00.000Z", sourceModule: "settings", permissionKey: "settings.update",
    authorizationOutcome: "requiresApproval", roleContext: "JP-ROL-0011", riskState: "low", changeSummary: "Booking alert preview toggle changed.", validationState: "warning",
    metadata: meta("203.0.113.60", "settings", { route: "/settings/notifications/preview" }),
  },
  {
    id: "JP-AUD-0054", type: "settings.integrationMetadataViewed", category: "settings", severity: "info", outcome: "preview",
    actor: U.u18, target: { type: "integration", id: "JP-INT-SABRE", label: "Sabre connection metadata" },
    actionLabel: "Integration metadata viewed (preview)", summary: "Preview-only supplier integration metadata viewed — credentials masked.",
    occurredAt: "2026-06-28T14:15:00.000Z", sourceModule: "settings", permissionKey: "settings.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0013", riskState: "none", changeSummary: null, validationState: "valid",
    metadata: meta("203.0.113.61", "settings", { route: "/settings/integrations/JP-INT-SABRE", channel: "gds" }),
  },

  // security (6)
  {
    id: "JP-AUD-0055", type: "security.highRiskPermissionDetected", category: "security", severity: "critical", outcome: "preview",
    actor: systemActor, target: { type: "permission", id: "users.assignRoles", label: "Assign user roles" },
    actionLabel: "High-risk permission detected (preview)", summary: "Preview-only high-risk permission flagged during access validation scan.",
    occurredAt: "2026-06-30T07:00:00.000Z", sourceModule: "security", permissionKey: "users.assignRoles",
    authorizationOutcome: "notApplicable", roleContext: null, riskState: "critical", changeSummary: null, validationState: "warning",
    metadata: { ...meta("192.0.2.80", "security"), maskedIp: null, maskedNetworkRange: null, userAgentSummary: null, retentionCategory: "security", referenceLabel: "SEC-SCAN-0055" },
  },
  {
    id: "JP-AUD-0056", type: "security.protectedRoleReview", category: "security", severity: "warning", outcome: "preview",
    actor: U.u09, target: { type: "role", id: "JP-ROL-0001", label: "Super Administrator" },
    actionLabel: "Protected role review (preview)", summary: "Preview-only protected role access review opened by auditor.",
    occurredAt: "2026-06-30T07:05:00.000Z", sourceModule: "security", permissionKey: "roles.view",
    authorizationOutcome: "allowed", roleContext: "JP-ROL-0009", riskState: "high", changeSummary: null, validationState: "valid",
    metadata: meta("198.51.100.90", "security", { route: "/users/roles/JP-ROL-0001/review", retentionCategory: "security" }),
  },
  {
    id: "JP-AUD-0057", type: "security.excessiveAccessWarning", category: "security", severity: "warning", outcome: "preview",
    actor: systemActor, target: { type: "user", id: "JP-USR-0019", label: "Maria J." },
    actionLabel: "Excessive access warning (preview)", summary: "Preview-only excessive access warning — broad role assignment detected in fixture.",
    occurredAt: "2026-06-29T08:00:00.000Z", sourceModule: "security", permissionKey: null,
    authorizationOutcome: "notApplicable", roleContext: "JP-ROL-0012", riskState: "high", changeSummary: null, validationState: "warning",
    metadata: { ...meta("203.0.113.70", "security"), maskedIp: null, maskedNetworkRange: null, userAgentSummary: null, retentionCategory: "security", referenceLabel: "SEC-ACCESS-0057" },
  },
  {
    id: "JP-AUD-0058", type: "security.unsafeLinkValidation", category: "security", severity: "critical", outcome: "failure",
    actor: U.u06, target: { type: "cmsPage", id: "JP-CMS-PG-004", label: "External links page" },
    actionLabel: "Unsafe link validation (preview)", summary: "Preview-only unsafe external link blocked during CMS validation scan.",
    occurredAt: "2026-06-27T11:00:00.000Z", sourceModule: "security", permissionKey: "cms.review",
    authorizationOutcome: "denied", roleContext: "JP-ROL-0006", riskState: "critical", changeSummary: "Non-allowlisted URL rejected in preview.", validationState: "blocked",
    metadata: meta("192.0.2.81", "security", { route: "/cms/pages/JP-CMS-PG-004/validation", retentionCategory: "security", referenceLabel: "LINK-BLOCK-0058" }),
  },
  {
    id: "JP-AUD-0059", type: "security.warningDetected", category: "security", severity: "warning", outcome: "preview",
    actor: systemActor, target: { type: "user", id: "JP-USR-0015", label: "Sadia F." },
    actionLabel: "Security warning detected (preview)", summary: "Preview-only security warning: suspended account with active preview sessions.",
    occurredAt: "2026-06-30T08:00:00.000Z", sourceModule: "security", permissionKey: null,
    authorizationOutcome: "notApplicable", roleContext: null, riskState: "elevated", changeSummary: null, validationState: "blocked",
    metadata: { ...meta("198.51.100.91", "security"), maskedIp: null, maskedNetworkRange: null, userAgentSummary: null, retentionCategory: "security", referenceLabel: "SEC-WARN-0059" },
  },
  {
    id: "JP-AUD-0060", type: "permission.authorizationPreviewEvaluated", category: "security", severity: "warning", outcome: "failure",
    actor: U.u03, target: { type: "permission", id: "settings.update", label: "Update settings" },
    actionLabel: "Authorization denied (preview)", summary: "Preview-only settings update authorization denied for booking agent role.",
    occurredAt: "2026-06-01T09:00:00.000Z", sourceModule: "security", permissionKey: "settings.update",
    authorizationOutcome: "denied", roleContext: "JP-ROL-0003", riskState: "high", changeSummary: null, validationState: "valid",
    metadata: meta("203.0.113.80", "security", { route: "/settings/general/preview", referenceLabel: "AUTH-DENY-0060", retentionCategory: "security" }),
  },
];

export const mockAuditEvents: AuditEvent[] = EVENT_SEEDS.map(buildEvent);

export const AUDIT_FIXTURE_COUNT = mockAuditEvents.length;

const auditEventById = new Map(mockAuditEvents.map((event) => [event.id, event]));

export function getAuditEventById(id: string): AuditEvent | undefined {
  return auditEventById.get(id);
}
