import { getUserById } from "@/mocks/user-fixtures";
import { mockRoles } from "@/mocks/rbac-fixtures";
import { PERMISSION_BY_KEY } from "@/lib/access-control/permission-catalog";
import type {
  AccessValidationIssue,
  AccessValidationResult,
  AuditActorType,
  AuditCategory,
  AuditChannel,
  AuditEvent,
  AuditEventType,
  AuditOutcome,
  AuditSeverity,
  AuditTargetType,
} from "@/types/access-control";

const VALID_ACTOR_TYPES: AuditActorType[] = [
  "dashboardUser",
  "system",
  "supplierChannel",
  "anonymousPreview",
  "scheduledProcess",
];

const VALID_CATEGORIES: AuditCategory[] = [
  "authentication",
  "users",
  "roles",
  "permissions",
  "bookings",
  "operations",
  "reports",
  "cms",
  "settings",
  "security",
];

const VALID_SEVERITIES: AuditSeverity[] = ["info", "notice", "warning", "critical"];
const VALID_OUTCOMES: AuditOutcome[] = ["success", "failure", "partial", "preview"];
const VALID_CHANNELS: AuditChannel[] = ["gds", "ndc", "oneApi", "manual", "mock", "dashboard", null];

const VALID_TARGET_TYPES: AuditTargetType[] = [
  "user",
  "role",
  "permission",
  "booking",
  "payment",
  "customer",
  "supplier",
  "agent",
  "pnrOrder",
  "ticketDocument",
  "report",
  "cmsPage",
  "cmsSection",
  "setting",
  "integration",
  "auditEvent",
  "dashboard",
];

const VALID_EVENT_TYPES: AuditEventType[] = [
  "auth.signedIn",
  "auth.signInFailed",
  "auth.accountLockWarning",
  "auth.mfaChallenge",
  "auth.invitationViewed",
  "auth.invitationExpired",
  "auth.sessionPolicyWarning",
  "user.recordViewed",
  "user.rolePreviewOpened",
  "user.rolePreviewApplied",
  "user.securityReviewOpened",
  "role.viewed",
  "permission.catalogueViewed",
  "role.comparisonOpened",
  "permission.assignmentPreviewOpened",
  "permission.authorizationPreviewEvaluated",
  "permission.matrixViewed",
  "booking.viewed",
  "payment.viewed",
  "pnr.viewed",
  "ticket.viewed",
  "cancellation.eligibilityViewed",
  "ticketing.readinessViewed",
  "report.viewed",
  "report.filterChanged",
  "report.exportPreviewGenerated",
  "cms.pagePreviewViewed",
  "cms.sectionPreviewChanged",
  "cms.validationWarningViewed",
  "cms.bannerPreviewViewed",
  "settings.viewed",
  "settings.generalPreviewChanged",
  "settings.securityPreviewViewed",
  "settings.notificationPreviewChanged",
  "settings.integrationMetadataViewed",
  "security.highRiskPermissionDetected",
  "security.protectedRoleReview",
  "security.excessiveAccessWarning",
  "security.unsafeLinkValidation",
  "security.warningDetected",
];

const UNSAFE_METADATA_KEYS = [
  "password",
  "passwordHash",
  "token",
  "sessionId",
  "cookie",
  "authorization",
  "apiKey",
  "secret",
  "mfaSecret",
  "pcc",
  "lniata",
  "webhookSecret",
  "smtpPassword",
];

const FAKE_MUTATION_PATTERNS = [
  /\blive save\b/i,
  /\bpersisted to production\b/i,
  /\brole mutation applied\b/i,
  /\baccount suspended\b/i,
  /\brefund processed\b/i,
  /\bticket issued\b/i,
  /\bcancellation completed\b/i,
];

const SECRET_SUMMARY_PATTERNS = [
  /passwordhash/i,
  /\bapikey\b/i,
  /\bsessionid\b/i,
  /\bbearer\b/i,
  /\bwebhooksecret\b/i,
  /\bsmtppassword\b/i,
];

const TEST_NET_PREFIXES = ["192.0.2.", "198.51.100.", "203.0.113."];

function issue(
  severity: AccessValidationIssue["severity"],
  code: string,
  message: string,
  fieldPath: string,
  entityId: string,
  suggestedResolution: string,
  blocking: boolean,
): AccessValidationIssue {
  return { severity, code, message, fieldPath, entityId, suggestedResolution, blocking };
}

function isTestNetIp(ip: string | null): boolean {
  if (!ip) return true;
  return TEST_NET_PREFIXES.some((p) => ip.startsWith(p));
}

function looksLikeUnmaskedIp(ip: string): boolean {
  if (isTestNetIp(ip)) return false;
  return /^\d{1,3}(\.\d{1,3}){3}$/.test(ip);
}

function metadataContainsSecrets(event: AuditEvent): AccessValidationIssue[] {
  const issues: AccessValidationIssue[] = [];
  const metadataBlob = JSON.stringify(event.metadata).toLowerCase();
  const sensitiveValues = ["bearer ", "sessionid=", "csrf=", "password=", "api_key"];
  for (const key of UNSAFE_METADATA_KEYS) {
    if (Object.prototype.hasOwnProperty.call(event.metadata, key)) {
      issues.push(
        issue(
          "error",
          "AUDIT_SECRET_METADATA",
          `Unsafe metadata key detected: ${key}`,
          `metadata.${key}`,
          event.id,
          "Remove sensitive metadata from audit fixtures.",
          true,
        ),
      );
    }
  }
  for (const pattern of sensitiveValues) {
    if (metadataBlob.includes(pattern)) {
      issues.push(
        issue(
          "error",
          "AUDIT_SECRET_METADATA",
          `Secret-like metadata value detected.`,
          "metadata",
          event.id,
          "Remove sensitive metadata from audit fixtures.",
          true,
        ),
      );
      break;
    }
  }
  return issues;
}

export function validateAuditEvent(event: AuditEvent): AccessValidationResult {
  const issues: AccessValidationIssue[] = [];

  if (!event.actor.displayName?.trim()) {
    issues.push(
      issue("error", "AUDIT_MISSING_ACTOR", "Actor display name is required.", "actor.displayName", event.id, "Provide actor metadata.", true),
    );
  }

  if (!event.target.label?.trim()) {
    issues.push(
      issue("error", "AUDIT_MISSING_TARGET", "Target label is required.", "target.label", event.id, "Provide target metadata.", true),
    );
  }

  if (!VALID_EVENT_TYPES.includes(event.type)) {
    issues.push(
      issue("error", "AUDIT_INVALID_EVENT_TYPE", `Unsupported event type: ${event.type}`, "type", event.id, "Use a catalogued event type.", true),
    );
  }

  if (!VALID_CATEGORIES.includes(event.category)) {
    issues.push(
      issue("error", "AUDIT_INVALID_CATEGORY", `Unsupported category: ${event.category}`, "category", event.id, "Use a catalogued category.", true),
    );
  }

  if (!VALID_SEVERITIES.includes(event.severity)) {
    issues.push(
      issue("error", "AUDIT_INVALID_SEVERITY", `Unsupported severity: ${event.severity}`, "severity", event.id, "Use info, notice, warning, or critical.", true),
    );
  }

  if (!VALID_OUTCOMES.includes(event.outcome)) {
    issues.push(
      issue("error", "AUDIT_INVALID_OUTCOME", `Unsupported outcome: ${event.outcome}`, "outcome", event.id, "Use success, failure, partial, or preview.", true),
    );
  }

  if (!VALID_ACTOR_TYPES.includes(event.actor.actorType)) {
    issues.push(
      issue("error", "AUDIT_INVALID_ACTOR_TYPE", `Unsupported actor type: ${event.actor.actorType}`, "actor.actorType", event.id, "Use a catalogued actor type.", true),
    );
  }

  if (!VALID_TARGET_TYPES.includes(event.target.type)) {
    issues.push(
      issue("error", "AUDIT_INVALID_TARGET_TYPE", `Unsupported target type: ${event.target.type}`, "target.type", event.id, "Use a catalogued target type.", true),
    );
  }

  if (!VALID_CHANNELS.includes(event.metadata.channel)) {
    issues.push(
      issue("error", "AUDIT_INVALID_CHANNEL", `Unsupported channel: ${String(event.metadata.channel)}`, "metadata.channel", event.id, "Use a catalogued channel.", true),
    );
  }

  if (!event.occurredAt || Number.isNaN(new Date(event.occurredAt).getTime())) {
    issues.push(
      issue("error", "AUDIT_INVALID_TIMESTAMP", "Timestamp is invalid.", "occurredAt", event.id, "Use ISO-8601 timestamps.", true),
    );
  }

  if (event.metadata.maskedIp && looksLikeUnmaskedIp(event.metadata.maskedIp)) {
    issues.push(
      issue("error", "AUDIT_UNMASKED_IP", "IP must use TEST-NET masking.", "metadata.maskedIp", event.id, "Use 192.0.2.x, 198.51.100.x, or 203.0.113.x.", true),
    );
  }

  if (event.metadata.maskedNetworkRange && looksLikeUnmaskedIp(event.metadata.maskedNetworkRange.split("/")[0] ?? "")) {
    issues.push(
      issue("error", "AUDIT_UNMASKED_NETWORK", "Network range must use TEST-NET masking.", "metadata.maskedNetworkRange", event.id, "Mask network metadata.", true),
    );
  }

  if (event.actor.actorType === "dashboardUser" && event.actor.userId) {
    if (!getUserById(event.actor.userId)) {
      issues.push(
        issue("error", "AUDIT_INVALID_ACTOR_REF", `Actor user not found: ${event.actor.userId}`, "actor.userId", event.id, "Reference a valid fixture user.", true),
      );
    }
  }

  if (event.target.type === "role") {
    if (!mockRoles.some((r) => r.id === event.target.id)) {
      issues.push(
        issue("warning", "AUDIT_INVALID_ROLE_TARGET", `Role target not found: ${event.target.id}`, "target.id", event.id, "Reference a valid fixture role.", false),
      );
    }
  }

  if (event.target.type === "permission" && event.target.id !== "matrix" && event.target.id !== "catalogue") {
    if (!PERMISSION_BY_KEY.has(event.target.id)) {
      issues.push(
        issue("warning", "AUDIT_INVALID_PERMISSION_TARGET", `Permission target not found: ${event.target.id}`, "target.id", event.id, "Reference a valid permission key.", false),
      );
    }
  }

  if (event.permissionKey && !PERMISSION_BY_KEY.has(event.permissionKey)) {
    issues.push(
      issue("warning", "AUDIT_INVALID_PERMISSION_KEY", `Permission key not in catalog: ${event.permissionKey}`, "permissionKey", event.id, "Use catalog permission keys.", false),
    );
  }

  if (!event.metadata.retentionCategory) {
    issues.push(
      issue("warning", "AUDIT_RETENTION_MISSING", "Retention category is missing.", "metadata.retentionCategory", event.id, "Set retention metadata.", false),
    );
  }

  const previewEventTypes: AuditEventType[] = [
    "user.rolePreviewApplied",
    "cms.sectionPreviewChanged",
    "settings.generalPreviewChanged",
    "settings.notificationPreviewChanged",
    "report.exportPreviewGenerated",
  ];
  if (previewEventTypes.includes(event.type) && !event.metadata.previewOnly) {
    issues.push(
      issue("error", "AUDIT_MISSING_PREVIEW_MARKER", "Local preview event must be marked preview-only.", "metadata.previewOnly", event.id, "Set previewOnly to true.", true),
    );
  }

  for (const pattern of FAKE_MUTATION_PATTERNS) {
    if (pattern.test(event.summary) || (event.changeSummary && pattern.test(event.changeSummary))) {
      issues.push(
        issue("error", "AUDIT_FAKE_MUTATION", "Event claims a live mutation.", "summary", event.id, "Use preview-only wording.", true),
      );
      break;
    }
  }

  for (const pattern of SECRET_SUMMARY_PATTERNS) {
    const haystack = `${event.summary} ${event.changeSummary ?? ""}`;
    if (pattern.test(haystack)) {
      issues.push(
        issue("error", "AUDIT_SECRET_METADATA", "Secret-like content detected in event text.", "summary", event.id, "Remove sensitive content from audit fixtures.", true),
      );
      break;
    }
  }

  issues.push(...metadataContainsSecrets(event));

  const blocking = issues.some((i) => i.blocking);
  return { valid: !blocking && issues.length === 0, issues };
}

export function validateAuditCatalog(events: AuditEvent[]): AccessValidationResult {
  const issues: AccessValidationIssue[] = [];
  const ids = events.map((e) => e.id);
  const unique = new Set(ids);
  if (unique.size !== ids.length) {
    const dupes = ids.filter((id, i) => ids.indexOf(id) !== i);
    issues.push(
      issue("error", "AUDIT_DUPLICATE_ID", `Duplicate audit IDs: ${[...new Set(dupes)].join(", ")}`, "id", dupes[0] ?? "", "Ensure unique event IDs.", true),
    );
  }

  for (const event of events) {
    const result = validateAuditEvent(event);
    issues.push(...result.issues);
  }

  const blocking = issues.some((i) => i.blocking);
  return { valid: !blocking && issues.length === 0, issues };
}

export const SECURITY_EVENT_TYPES: AuditEventType[] = [
  "auth.signInFailed",
  "auth.accountLockWarning",
  "auth.mfaChallenge",
  "auth.sessionPolicyWarning",
  "security.highRiskPermissionDetected",
  "security.protectedRoleReview",
  "security.excessiveAccessWarning",
  "security.unsafeLinkValidation",
  "security.warningDetected",
  "permission.authorizationPreviewEvaluated",
];

export function isSecurityAuditEvent(event: AuditEvent): boolean {
  if (SECURITY_EVENT_TYPES.includes(event.type)) return true;
  if (event.category === "security") return true;
  if (event.authorizationOutcome === "denied") return true;
  return false;
}

export {
  VALID_ACTOR_TYPES,
  VALID_CATEGORIES,
  VALID_SEVERITIES,
  VALID_OUTCOMES,
  VALID_CHANNELS,
  VALID_TARGET_TYPES,
  VALID_EVENT_TYPES,
  TEST_NET_PREFIXES,
};
