import { DashboardLink } from "@/components/dashboard/dashboard-link";
import { Card, CardDescription, CardTitle } from "@/components/ui/card";
import { CmsStatusBadge } from "@/components/ui/status-badge";
import { formatDateTime } from "@/lib/format";
import { SettingsValidationSummary } from "@/features/settings/components/settings-validation-summary";
import type { SettingsModuleResult } from "@/types/settings-module";

const STATE_LABELS: Record<string, string> = {
  ready: "Ready",
  warning: "Warning",
  incomplete: "Incomplete",
};

type Props = {
  result: SettingsModuleResult;
};

export function SettingsOverview({ result }: Props) {
  const { overview } = result;
  const metrics = [
    { key: "generalState", label: "General", value: STATE_LABELS[overview.generalState] ?? overview.generalState },
    { key: "securityPolicyState", label: "Security policy", value: STATE_LABELS[overview.securityPolicyState] ?? overview.securityPolicyState },
    { key: "notificationState", label: "Notifications", value: STATE_LABELS[overview.notificationState] ?? overview.notificationState },
    { key: "integrationState", label: "Integrations", value: STATE_LABELS[overview.integrationState] ?? overview.integrationState },
    { key: "settingsRequiringReview", label: "Requiring review", value: overview.settingsRequiringReview },
    { key: "highRiskPolicyWarnings", label: "High-risk warnings", value: overview.highRiskPolicyWarnings },
    { key: "incompleteMetadata", label: "Incomplete metadata", value: overview.incompleteMetadata },
    { key: "lastFixtureRevision", label: "Source snapshot", value: formatDateTime(overview.lastFixtureRevision) },
  ] as const;

  return (
    <div className="space-y-4" data-testid="settings-overview">
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8" data-testid="settings-metric-grid">
        {metrics.map((metric) => (
          <Card key={metric.key} className="p-3">
            <CardDescription className="text-xs">{metric.label}</CardDescription>
            <CardTitle className="mt-1 text-base tabular-nums">{metric.value}</CardTitle>
          </Card>
        ))}
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <section className="rounded-xl border border-jp-border bg-white p-4" aria-labelledby="category-readiness-heading">
          <h3 id="category-readiness-heading" className="text-sm font-semibold text-gray-900">
            Category readiness
          </h3>
          <ul className="mt-3 space-y-2" role="list">
            {result.categoryReadiness.map((category) => {
              const sectionIssues = result.validationIssues.filter(
                (issue) => issue.entityId === `settings-${category.section}`,
              );
              const issueCount = Math.max(category.issueCount, sectionIssues.length);
              const blocking = sectionIssues.some((issue) => issue.blocking) || !category.ready;
              const ownerInputOnly =
                sectionIssues.length > 0 &&
                sectionIssues.every((issue) => issue.suggestedResolution.startsWith("OWNER_INPUT_REQUIRED"));
              let badgeStatus = "valid";
              let badgeLabel = "Ready";
              if (blocking) {
                badgeStatus = "blocked";
                badgeLabel = "Blocked";
              } else if (ownerInputOnly) {
                badgeStatus = "review";
                badgeLabel = "Owner input required";
              } else if (issueCount > 0) {
                badgeStatus = "warning";
                badgeLabel = "Warning";
              }

              return (
                <li
                  key={category.section}
                  className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-jp-border px-3 py-2 text-sm"
                >
                  <div>
                    <p className="font-medium text-gray-900">{category.label}</p>
                    <p className="text-xs text-jp-muted">
                      {issueCount} validation issue{issueCount === 1 ? "" : "s"}
                      {ownerInputOnly ? " (owner input)" : ""}
                    </p>
                  </div>
                  <div className="flex items-center gap-2">
                    <CmsStatusBadge status={badgeStatus} label={badgeLabel} />
                    <DashboardLink
                      href={`/settings/${category.section}`}
                      className="text-xs font-medium text-jp-accent hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                    >
                      Open
                    </DashboardLink>
                  </div>
                </li>
              );
            })}
          </ul>
        </section>

        <SettingsValidationSummary
          issues={result.validationIssues}
          filter={result.query.validationState}
          title="Validation summary"
        />
      </div>

      <div
        role="note"
        className="rounded-xl border border-jp-border bg-gray-50 px-4 py-3 text-sm text-gray-800"
        data-testid="settings-laravel-boundary-note"
      >
        <p className="font-medium">Saved configuration</p>
        <p className="mt-1">
          Support contacts, timezone, and company identity come from Organization profile. Warnings remain until those
          saved values are present. They are not suppressed.
        </p>
      </div>
    </div>
  );
}
