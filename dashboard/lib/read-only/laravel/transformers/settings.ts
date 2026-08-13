import type { AccessValidationIssue } from "@/types/access-control";
import {
  validateAllSettings,
  validateGeneralSettings,
  validateIntegrationSettings,
  validateNotificationSettings,
  validateSecuritySettings,
} from "@/lib/access-control/settings-validation";
import type { LaravelSettingsPayload } from "@/lib/read-only/laravel/types";
import type {
  GeneralSettingsValues,
  IntegrationRecord,
  IntegrationSettingsValues,
  NotificationSettingsValues,
  SecuritySettingsValues,
  SettingsModuleResult,
  SettingsQuery,
} from "@/types/settings-module";

function readinessState(issueCount: number, blocking: number): "ready" | "warning" | "incomplete" {
  if (blocking > 0) return "incomplete";
  if (issueCount > 0) return "warning";
  return "ready";
}

function normalizeIntegrations(values: IntegrationSettingsValues): IntegrationSettingsValues {
  return {
    integrations: (values.integrations ?? []).map((item): IntegrationRecord => ({
      key: String(item.key ?? ""),
      displayName: String(item.displayName ?? ""),
      channel: String(item.channel ?? ""),
      enabled: Boolean(item.enabled),
      readinessStatus: String(item.readinessStatus ?? "not_configured"),
      environmentLabel: String(item.environmentLabel ?? "sandbox"),
      lastSyntheticCheck: String(item.lastSyntheticCheck ?? ""),
      configurationCompleteness: Number(item.configurationCompleteness ?? 0),
      capabilitySummary: String(item.capabilitySummary ?? ""),
      warningState: Boolean(item.warningState),
      futureOwner: String(item.futureOwner ?? ""),
      documentationReference: String((item as IntegrationRecord).documentationReference ?? ""),
    })),
  };
}

function buildOverviewFromIssues(
  generalIssues: AccessValidationIssue[],
  securityIssues: AccessValidationIssue[],
  notificationIssues: AccessValidationIssue[],
  integrationIssues: AccessValidationIssue[],
  snapshotLabel: string,
): SettingsModuleResult["overview"] {
  const allIssues = [...generalIssues, ...securityIssues, ...notificationIssues, ...integrationIssues];

  return {
    generalState: readinessState(generalIssues.length, generalIssues.filter((i) => i.blocking).length),
    securityPolicyState: readinessState(securityIssues.length, securityIssues.filter((i) => i.blocking).length),
    notificationState: readinessState(notificationIssues.length, notificationIssues.filter((i) => i.blocking).length),
    integrationState: readinessState(integrationIssues.length, integrationIssues.filter((i) => i.blocking).length),
    settingsRequiringReview: allIssues.filter((i) => i.severity === "warning").length,
    highRiskPolicyWarnings: securityIssues.filter((i) => i.code.includes("HIGH_RISK") || i.code.includes("MFA")).length,
    incompleteMetadata: allIssues.filter((i) => i.blocking).length,
    lastFixtureRevision: snapshotLabel,
  };
}

function buildCategoryReadiness(
  generalIssues: AccessValidationIssue[],
  securityIssues: AccessValidationIssue[],
  notificationIssues: AccessValidationIssue[],
  integrationIssues: AccessValidationIssue[],
): SettingsModuleResult["categoryReadiness"] {
  return [
    {
      section: "general",
      label: "General",
      ready: !generalIssues.some((i) => i.blocking),
      issueCount: generalIssues.length,
    },
    {
      section: "security",
      label: "Security",
      ready: !securityIssues.some((i) => i.blocking),
      issueCount: securityIssues.length,
    },
    {
      section: "notifications",
      label: "Notifications",
      ready: !notificationIssues.some((i) => i.blocking),
      issueCount: notificationIssues.length,
    },
    {
      section: "integrations",
      label: "Integrations",
      ready: !integrationIssues.some((i) => i.blocking),
      issueCount: integrationIssues.length,
    },
  ];
}

export function transformSettingsModule(
  overview: LaravelSettingsPayload,
  general: GeneralSettingsValues,
  security: SecuritySettingsValues,
  notifications: NotificationSettingsValues,
  integrations: IntegrationSettingsValues,
  query: SettingsQuery,
): SettingsModuleResult {
  const normalizedIntegrations = normalizeIntegrations(integrations);
  const generalIssues = validateGeneralSettings(general);
  const securityIssues = validateSecuritySettings(security);
  const notificationIssues = validateNotificationSettings(notifications);
  const integrationIssues = validateIntegrationSettings(normalizedIntegrations);
  const snapshotLabel = String(overview.lastFixtureRevision ?? "platform settings");

  return {
    state: "ready",
    query,
    overview: buildOverviewFromIssues(
      generalIssues,
      securityIssues,
      notificationIssues,
      integrationIssues,
      snapshotLabel,
    ),
    general,
    security,
    notifications,
    integrations: normalizedIntegrations,
    validationIssues: validateAllSettings(general, security, notifications, normalizedIntegrations),
    categoryReadiness: buildCategoryReadiness(
      generalIssues,
      securityIssues,
      notificationIssues,
      integrationIssues,
    ),
  };
}
