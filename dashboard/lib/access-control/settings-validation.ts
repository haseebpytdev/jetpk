import type { AccessValidationIssue } from "@/types/access-control";
import type {
  GeneralSettingsValues,
  IntegrationSettingsValues,
  NotificationSettingsValues,
  SecuritySettingsValues,
} from "@/types/settings-module";

const SUPPORTED_TIMEZONES = new Set(["Asia/Karachi", "UTC", "Asia/Dubai", "Europe/London"]);
const SUPPORTED_CURRENCIES = new Set(["PKR", "USD", "AED", "GBP"]);
const SUPPORTED_LOCALES = new Set(["en-PK", "en-US", "en-GB"]);
const SUPPORTED_DATE_FORMATS = new Set(["DD MMM YYYY", "YYYY-MM-DD", "MM/DD/YYYY"]);
const SECRET_PATTERN = /(password|secret|token|apikey|api_key|pcc|lniata|smtp|webhook)/i;

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

function isValidEmail(email: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function isValidPhone(phone: string): boolean {
  return /^\+?[\d\s()-]{7,20}$/.test(phone);
}

const SUPPORTED_INTEGRATION_ENV_LABELS = new Set([
  "demo",
  "sandbox",
  "live",
  "preview",
  "not_configured",
  "staging",
  "production",
  "cert",
  "test",
]);

function isBlankOrPlaceholder(value: string): boolean {
  const trimmed = value.trim();
  return trimmed === "" || trimmed === "—" || trimmed.toLowerCase() === "n/a" || trimmed.toLowerCase() === "pending";
}

export function validateGeneralSettings(values: GeneralSettingsValues): AccessValidationIssue[] {
  const issues: AccessValidationIssue[] = [];
  const id = "settings-general";

  if (!values.organizationDisplayName.trim() || isBlankOrPlaceholder(values.organizationDisplayName)) {
    issues.push(issue("warning", "SETTINGS_ORG_LABEL_MISSING", "Organization display name is missing from configuration.", "organizationDisplayName", id, "OWNER_INPUT_REQUIRED: set organization display name when authoritative branding metadata exists.", false));
  }
  if (isBlankOrPlaceholder(values.supportEmail)) {
    issues.push(issue("warning", "SETTINGS_SUPPORT_EMAIL_MISSING", "Support email is not configured in authoritative metadata.", "supportEmail", id, "OWNER_INPUT_REQUIRED: provide JetPakistan support email when available. Do not invent one.", false));
  } else if (!isValidEmail(values.supportEmail)) {
    issues.push(issue("error", "SETTINGS_INVALID_EMAIL", "Support email format is invalid.", "supportEmail", id, "Use a valid email address from authoritative configuration.", true));
  }
  if (isBlankOrPlaceholder(values.supportPhone)) {
    issues.push(issue("warning", "SETTINGS_SUPPORT_PHONE_MISSING", "Support phone is not configured in authoritative metadata.", "supportPhone", id, "OWNER_INPUT_REQUIRED: provide JetPakistan support phone when available. Do not invent one.", false));
  } else if (!isValidPhone(values.supportPhone)) {
    issues.push(issue("warning", "SETTINGS_INVALID_PHONE", "Support phone metadata format may be invalid.", "supportPhone", id, "Use international phone format from authoritative configuration.", false));
  }
  if (!SUPPORTED_TIMEZONES.has(values.timezone)) {
    issues.push(issue("error", "SETTINGS_UNSUPPORTED_TIMEZONE", "Timezone is not supported.", "timezone", id, "Select a supported timezone.", true));
  }
  if (!SUPPORTED_CURRENCIES.has(values.defaultCurrency)) {
    issues.push(issue("error", "SETTINGS_UNSUPPORTED_CURRENCY", "Currency is not supported.", "defaultCurrency", id, "Select a supported currency.", true));
  }
  if (!SUPPORTED_LOCALES.has(values.locale)) {
    issues.push(issue("error", "SETTINGS_UNSUPPORTED_LOCALE", "Locale is not supported.", "locale", id, "Select a supported locale.", true));
  }
  if (!SUPPORTED_DATE_FORMATS.has(values.dateFormat)) {
    issues.push(issue("error", "SETTINGS_INVALID_DATE_FORMAT", "Date format is not supported.", "dateFormat", id, "Select a supported date format.", true));
  }

  return issues;
}

export function validateSecuritySettings(values: SecuritySettingsValues): AccessValidationIssue[] {
  const issues: AccessValidationIssue[] = [];
  const id = "settings-security";

  if (values.passwordMinLength < 10) {
    issues.push(issue("warning", "SETTINGS_WEAK_PASSWORD_LENGTH", "Password minimum length metadata is below recommended threshold.", "passwordMinLength", id, "Set minimum length to at least 10.", false));
  }
  if (values.privilegedRoleMfaPolicy === "disabled") {
    issues.push(issue("warning", "SETTINGS_PRIVILEGED_MFA_DISABLED", "Privileged role MFA policy is disabled.", "privilegedRoleMfaPolicy", id, "Enable MFA for privileged roles.", false));
  }
  if (values.sessionDurationHours > 24) {
    issues.push(issue("warning", "SETTINGS_EXCESSIVE_SESSION", "Session duration exceeds recommended maximum.", "sessionDurationHours", id, "Reduce session duration.", false));
  }
  if (values.failedLoginThreshold > 10) {
    issues.push(issue("warning", "SETTINGS_HIGH_LOGIN_THRESHOLD", "Failed login threshold is too high.", "failedLoginThreshold", id, "Lower the failed login threshold.", false));
  }
  if (values.lockoutDurationMinutes <= 0) {
    issues.push(issue("error", "SETTINGS_LOCKOUT_MISSING", "Lockout duration metadata is missing.", "lockoutDurationMinutes", id, "Set a positive lockout duration.", true));
  }
  if (values.highRiskApprovalPolicy === "disabled") {
    issues.push(issue("warning", "SETTINGS_HIGH_RISK_APPROVAL_DISABLED", "High-risk approval policy is disabled.", "highRiskApprovalPolicy", id, "Enable high-risk approval policy.", false));
  }
  if (values.auditRetentionDays <= 0) {
    issues.push(issue("error", "SETTINGS_AUDIT_RETENTION_MISSING", "Audit retention metadata is missing.", "auditRetentionDays", id, "Set audit retention days.", true));
  }

  return issues;
}

export function validateNotificationSettings(values: NotificationSettingsValues): AccessValidationIssue[] {
  const issues: AccessValidationIssue[] = [];
  const id = "settings-notifications";

  for (const cat of values.categories) {
    if (cat.enabled && !cat.emailChannel && !cat.dashboardChannel) {
      issues.push(issue("error", "SETTINGS_NOTIFICATION_NO_CHANNEL", `${cat.label} is enabled without a delivery channel.`, `categories.${cat.key}.channels`, id, "Enable at least one delivery channel.", true));
    }
    if (cat.enabled && cat.recipientRoles.length === 0) {
      issues.push(issue("warning", "SETTINGS_NOTIFICATION_NO_RECIPIENT", `${cat.label} has no recipient role groups.`, `categories.${cat.key}.recipientRoles`, id, "Assign recipient roles.", false));
    }
    if (cat.deliveryMode === "digest" && !cat.emailChannel) {
      issues.push(issue("warning", "SETTINGS_INVALID_DIGEST", `${cat.label} digest mode requires email channel metadata.`, `categories.${cat.key}.deliveryMode`, id, "Enable email channel for digest.", false));
    }
  }

  const securityAlerts = values.categories.find((c) => c.key === "userSecurity");
  if (securityAlerts && !securityAlerts.enabled) {
    issues.push(issue("warning", "SETTINGS_SECURITY_ALERTS_DISABLED", "Security alerts are disabled.", "categories.userSecurity.enabled", id, "Enable security alerts.", false));
  }

  const auditAlerts = values.categories.find((c) => c.key === "audit");
  if (auditAlerts && !auditAlerts.enabled) {
    issues.push(issue("warning", "SETTINGS_AUDIT_ALERTS_DISABLED", "Audit alerts are disabled.", "categories.audit.enabled", id, "Enable audit alerts.", false));
  }

  return issues;
}

export function validateIntegrationSettings(values: IntegrationSettingsValues): AccessValidationIssue[] {
  const issues: AccessValidationIssue[] = [];
  const id = "settings-integrations";

  for (const integration of values.integrations) {
    if (integration.enabled && integration.configurationCompleteness < 50) {
      issues.push(issue("warning", "SETTINGS_INTEGRATION_INCOMPLETE", `${integration.displayName} is enabled but configuration is incomplete.`, `integrations.${integration.key}.configurationCompleteness`, id, "Complete integration metadata or disable.", false));
    }
    if (integration.enabled && integration.readinessStatus.toLowerCase().includes("not configured")) {
      issues.push(issue("error", "SETTINGS_INTEGRATION_READINESS_CONFLICT", `${integration.displayName} readiness conflicts with enabled state.`, `integrations.${integration.key}.readinessStatus`, id, "Resolve readiness or disable integration.", true));
    }
    if (!integration.capabilitySummary.trim()) {
      issues.push(issue("warning", "SETTINGS_INTEGRATION_NO_CAPABILITY", `${integration.displayName} is missing capability metadata.`, `integrations.${integration.key}.capabilitySummary`, id, "Add capability summary.", false));
    }
    if (!SUPPORTED_INTEGRATION_ENV_LABELS.has(integration.environmentLabel.trim().toLowerCase())) {
      issues.push(issue("warning", "SETTINGS_UNSUPPORTED_ENV", `${integration.displayName} has unsupported environment label.`, `integrations.${integration.key}.environmentLabel`, id, "Use a canonical supplier environment label (demo, sandbox, live) or fixture labels (preview, staging, production).", false));
    }
    if (SECRET_PATTERN.test(integration.capabilitySummary) || SECRET_PATTERN.test(integration.documentationReference)) {
      issues.push(issue("error", "SETTINGS_SENSITIVE_KEY", "Sensitive key pattern detected in integration metadata.", `integrations.${integration.key}`, id, "Remove sensitive field references.", true));
    }
  }

  return issues;
}

export function validateAllSettings(
  general: GeneralSettingsValues,
  security: SecuritySettingsValues,
  notifications: NotificationSettingsValues,
  integrations: IntegrationSettingsValues,
): AccessValidationIssue[] {
  return [
    ...validateGeneralSettings(general),
    ...validateSecuritySettings(security),
    ...validateNotificationSettings(notifications),
    ...validateIntegrationSettings(integrations),
  ];
}
