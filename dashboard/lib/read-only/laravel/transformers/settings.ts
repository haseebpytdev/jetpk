import type { SettingsModuleResult, SettingsQuery } from "@/types/settings-module";
import type { LaravelSettingsPayload } from "@/lib/read-only/laravel/types";
import type { GeneralSettingsValues, IntegrationSettingsValues, NotificationSettingsValues, SecuritySettingsValues } from "@/types/settings-module";

export function transformSettingsModule(
  overview: LaravelSettingsPayload,
  general: GeneralSettingsValues,
  security: SecuritySettingsValues,
  notifications: NotificationSettingsValues,
  integrations: IntegrationSettingsValues,
  query: SettingsQuery,
): SettingsModuleResult {
  const categoryReadiness = Array.isArray(overview.categoryReadiness)
    ? (overview.categoryReadiness as SettingsModuleResult["categoryReadiness"])
    : [];

  return {
    state: "ready",
    query,
    overview: {
      generalState: (overview.generalState as SettingsModuleResult["overview"]["generalState"]) ?? "ready",
      securityPolicyState: (overview.securityPolicyState as SettingsModuleResult["overview"]["securityPolicyState"]) ?? "ready",
      notificationState: (overview.notificationState as SettingsModuleResult["overview"]["notificationState"]) ?? "ready",
      integrationState: (overview.integrationState as SettingsModuleResult["overview"]["integrationState"]) ?? "ready",
      settingsRequiringReview: Number(overview.settingsRequiringReview ?? 0),
      highRiskPolicyWarnings: Number(overview.highRiskPolicyWarnings ?? 0),
      incompleteMetadata: Number(overview.incompleteMetadata ?? 0),
      lastFixtureRevision: String(overview.lastFixtureRevision ?? "laravel-read-only"),
    },
    general,
    security,
    notifications,
    integrations,
    validationIssues: [],
    categoryReadiness,
  };
}
