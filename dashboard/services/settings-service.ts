import {
  validateAllSettings,
  validateGeneralSettings,
  validateIntegrationSettings,
  validateNotificationSettings,
  validateSecuritySettings,
} from "@/lib/access-control/settings-validation";
import { useMockData } from "@/lib/preview";
import {
  cloneGeneralSettings,
  cloneIntegrationSettings,
  cloneNotificationSettings,
  cloneSecuritySettings,
  FIXTURE_GENERAL_SETTINGS,
  FIXTURE_INTEGRATION_SETTINGS,
  FIXTURE_NOTIFICATION_SETTINGS,
  FIXTURE_SECURITY_SETTINGS,
  SETTINGS_FIXTURE_REVISION,
} from "@/mocks/settings-fixtures";
import type { SettingsSection } from "@/types/access-control";
import type { SettingsModuleResult, SettingsOverviewMetrics, SettingsQuery } from "@/types/settings-module";

export class SettingsServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "SettingsServiceError";
    this.referenceId = referenceId;
  }
}

function readinessState(issueCount: number, blocking: number): "ready" | "warning" | "incomplete" {
  if (blocking > 0) return "incomplete";
  if (issueCount > 0) return "warning";
  return "ready";
}

function buildOverview(): SettingsOverviewMetrics {
  const generalIssues = validateGeneralSettings(FIXTURE_GENERAL_SETTINGS);
  const securityIssues = validateSecuritySettings(FIXTURE_SECURITY_SETTINGS);
  const notificationIssues = validateNotificationSettings(FIXTURE_NOTIFICATION_SETTINGS);
  const integrationIssues = validateIntegrationSettings(FIXTURE_INTEGRATION_SETTINGS);
  const allIssues = [...generalIssues, ...securityIssues, ...notificationIssues, ...integrationIssues];

  return {
    generalState: readinessState(generalIssues.length, generalIssues.filter((i) => i.blocking).length),
    securityPolicyState: readinessState(securityIssues.length, securityIssues.filter((i) => i.blocking).length),
    notificationState: readinessState(notificationIssues.length, notificationIssues.filter((i) => i.blocking).length),
    integrationState: readinessState(integrationIssues.length, integrationIssues.filter((i) => i.blocking).length),
    settingsRequiringReview: allIssues.filter((i) => i.severity === "warning").length,
    highRiskPolicyWarnings: securityIssues.filter((i) => i.code.includes("HIGH_RISK") || i.code.includes("MFA")).length,
    incompleteMetadata: allIssues.filter((i) => i.blocking).length,
    lastFixtureRevision: SETTINGS_FIXTURE_REVISION,
  };
}

function buildCategoryReadiness(): SettingsModuleResult["categoryReadiness"] {
  const sections: { section: SettingsSection; label: string; issues: ReturnType<typeof validateGeneralSettings> }[] = [
    { section: "general", label: "General", issues: validateGeneralSettings(FIXTURE_GENERAL_SETTINGS) },
    { section: "security", label: "Security", issues: validateSecuritySettings(FIXTURE_SECURITY_SETTINGS) },
    { section: "notifications", label: "Notifications", issues: validateNotificationSettings(FIXTURE_NOTIFICATION_SETTINGS) },
    { section: "integrations", label: "Integrations", issues: validateIntegrationSettings(FIXTURE_INTEGRATION_SETTINGS) },
  ];
  return sections.map((s) => ({
    section: s.section,
    label: s.label,
    ready: !s.issues.some((i) => i.blocking),
    issueCount: s.issues.length,
  }));
}

export async function getSettingsModule(query: SettingsQuery): Promise<SettingsModuleResult> {
  if (!useMockData()) {
    throw new SettingsServiceError("Live settings are disabled in preview.", "SET-PREVIEW-NO-LIVE");
  }

  if (query.previewError) {
    throw new SettingsServiceError(
      "Mock settings service returned a recoverable error (preview simulation).",
      "SET-PREVIEW-SIM-ERR",
    );
  }

  await new Promise((r) => setTimeout(r, 60));

  if (query.previewLoading) {
    return {
      state: "loading",
      query,
      overview: {
        generalState: "ready",
        securityPolicyState: "ready",
        notificationState: "ready",
        integrationState: "ready",
        settingsRequiringReview: 0,
        highRiskPolicyWarnings: 0,
        incompleteMetadata: 0,
        lastFixtureRevision: SETTINGS_FIXTURE_REVISION,
      },
      general: cloneGeneralSettings(),
      security: cloneSecuritySettings(),
      notifications: cloneNotificationSettings(),
      integrations: cloneIntegrationSettings(),
      validationIssues: [],
      categoryReadiness: [],
    };
  }

  if (query.previewEmpty) {
    return {
      state: "empty",
      query,
      overview: buildOverview(),
      general: { ...cloneGeneralSettings(), organizationDisplayName: "" },
      security: cloneSecuritySettings(),
      notifications: { categories: [] },
      integrations: { integrations: [] },
      validationIssues: [],
      categoryReadiness: [],
    };
  }

  const general = cloneGeneralSettings();
  const security = cloneSecuritySettings();
  const notifications = cloneNotificationSettings();
  const integrations = cloneIntegrationSettings();
  const validationIssues = validateAllSettings(general, security, notifications, integrations);

  return {
    state: "ready",
    query,
    overview: buildOverview(),
    general,
    security,
    notifications,
    integrations,
    validationIssues,
    categoryReadiness: buildCategoryReadiness(),
  };
}
