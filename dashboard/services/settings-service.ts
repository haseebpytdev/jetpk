import {
  validateAllSettings,
  validateGeneralSettings,
  validateIntegrationSettings,
  validateNotificationSettings,
  validateSecuritySettings,
} from "@/lib/access-control/settings-validation";
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
import type {
  GeneralSettingsValues,
  IntegrationSettingsValues,
  NotificationSettingsValues,
  SecuritySettingsValues,
  SettingsModuleResult,
  SettingsQuery,
} from "@/types/settings-module";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { createReadOnlyService, ReadOnlyServiceError, type ReadOnlyFetchOptions } from "@/lib/read-only/read-only-service";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import { transformSettingsModule } from "@/lib/read-only/laravel/transformers/settings";

export class SettingsServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "SettingsServiceError";
    this.referenceId = referenceId;
  }
}

function mapReadOnlyError(error: unknown): never {
  if (error instanceof ReadOnlyServiceError) {
    throw new SettingsServiceError(error.envelope.error.message, error.envelope.error.referenceIdSafe);
  }
  throw error;
}

function readinessState(issueCount: number, blocking: number): "ready" | "warning" | "incomplete" {
  if (blocking > 0) return "incomplete";
  if (issueCount > 0) return "warning";
  return "ready";
}

function buildOverview(): SettingsModuleResult["overview"] {
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

function buildFixtureResult(query: SettingsQuery): SettingsModuleResult {
  if (query.previewLoading) {
    return {
      state: "loading",
      query,
      overview: buildOverview(),
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

  return {
    state: "ready",
    query,
    overview: buildOverview(),
    general,
    security,
    notifications,
    integrations,
    validationIssues: validateAllSettings(general, security, notifications, integrations),
    categoryReadiness: buildCategoryReadiness(),
  };
}

const settingsService = createReadOnlyService<SettingsQuery, SettingsModuleResult>({
  module: "settings",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(query, options) {
      if (query.previewError) {
        throw new ReadOnlyServiceError({
          error: {
            code: "internal_error",
            message: "Mock settings service returned a recoverable error (preview simulation).",
            referenceIdSafe: "SET-PREVIEW-SIM-ERR",
          },
          meta: { source: "fixture", schemaVersion: "dash-read-only-v1" },
        });
      }
      await new Promise((r) => setTimeout(r, 60));
      return createReadOnlyEnvelope({ data: buildFixtureResult(query), metadata: options?.metadata });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(query, options) {
      const [overview, general, security, notifications, integrations] = await Promise.all([
        fetchDashboardApi<Record<string, unknown>>(DASHBOARD_API_ROUTES.settings, { signal: options?.signal }),
        fetchDashboardApi<GeneralSettingsValues>(DASHBOARD_API_ROUTES.settingsGeneral, { signal: options?.signal }),
        fetchDashboardApi<SecuritySettingsValues>(DASHBOARD_API_ROUTES.settingsSecurity, { signal: options?.signal }),
        fetchDashboardApi<NotificationSettingsValues>(DASHBOARD_API_ROUTES.settingsNotifications, {
          signal: options?.signal,
        }),
        fetchDashboardApi<IntegrationSettingsValues>(DASHBOARD_API_ROUTES.settingsIntegrations, {
          signal: options?.signal,
        }),
      ]);
      return {
        ...overview,
        data: transformSettingsModule(
          overview.data,
          general.data,
          security.data,
          notifications.data,
          integrations.data,
          query,
        ),
      };
    },
  },
});

export async function getSettingsModule(query: SettingsQuery, options?: ReadOnlyFetchOptions): Promise<SettingsModuleResult> {
  try {
    const envelope = await settingsService.fetchReadOnly(query, options);
    return envelope.data;
  } catch (error) {
    mapReadOnlyError(error);
  }
}
