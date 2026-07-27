import Link from "next/link";
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
    { key: "lastFixtureRevision", label: "Fixture revision", value: formatDateTime(overview.lastFixtureRevision) },
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
            {result.categoryReadiness.map((category) => (
              <li key={category.section} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-jp-border px-3 py-2 text-sm">
                <div>
                  <p className="font-medium text-gray-900">{category.label}</p>
                  <p className="text-xs text-jp-muted">
                    {category.issueCount} validation issue{category.issueCount === 1 ? "" : "s"}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <CmsStatusBadge status={category.ready ? "valid" : "blocked"} label={category.ready ? "Ready" : "Blocked"} />
                  <Link
                    href={`/settings/${category.section}`}
                    className="text-xs font-medium text-jp-accent hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                  >
                    Open
                  </Link>
                </div>
              </li>
            ))}
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
        className="rounded-xl border border-violet-200 bg-violet-50/60 px-4 py-3 text-sm text-violet-900"
        data-testid="settings-laravel-boundary-note"
      >
        <p className="font-medium">Future Laravel boundary</p>
        <p className="mt-1">
          Settings persistence, secret rotation, and supplier credential storage will live in Laravel services behind
          authenticated admin APIs. This dashboard preview uses typed fixtures and local component state only — no
          credentials, PCC, LNIATA, or publish workflow.
        </p>
      </div>
    </div>
  );
}
