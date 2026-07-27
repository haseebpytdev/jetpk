import { AccessRiskBadge } from "@/components/ui/status-badge";
import type { EffectiveAccessDomainSummary, EffectiveAccessSummary } from "@/types/access-control";

export function AccessDomainGrid({ domains }: { domains: EffectiveAccessDomainSummary[] }) {
  if (domains.length === 0) {
    return <p className="text-sm text-jp-muted">No effective access from assigned roles.</p>;
  }

  return (
    <div className="grid gap-3 sm:grid-cols-2" data-testid="access-domain-grid" role="list" aria-label="Effective access by domain">
      {domains.map((domain) => (
        <div key={domain.domain} className="rounded-xl border border-jp-border p-3" role="listitem">
          <div className="flex items-center justify-between gap-2">
            <h4 className="text-sm font-semibold">{domain.label}</h4>
            {domain.highRiskCount > 0 ? (
              <AccessRiskBadge highRisk label={`${domain.highRiskCount} high risk`} />
            ) : null}
          </div>
          <p className="mt-1 text-xs text-jp-muted">{domain.permissionCount} permissions</p>
          <dl className="mt-2 grid grid-cols-2 gap-1 text-xs">
            <div><dt className="text-jp-muted">View</dt><dd>{domain.viewAccess ? "Yes" : "No"}</dd></div>
            <div><dt className="text-jp-muted">Request</dt><dd>{domain.requestAccess ? "Yes" : "No"}</dd></div>
            <div><dt className="text-jp-muted">Approve</dt><dd>{domain.approvalAccess ? "Yes" : "No"}</dd></div>
            <div><dt className="text-jp-muted">Manage</dt><dd>{domain.manageAccess ? "Yes" : "No"}</dd></div>
            <div><dt className="text-jp-muted">Export</dt><dd>{domain.exportAccess ? "Yes" : "No"}</dd></div>
          </dl>
        </div>
      ))}
    </div>
  );
}

export function EffectiveAccessSummaryPanel({ summary, testId = "effective-access-summary" }: { summary: EffectiveAccessSummary; testId?: string }) {
  return (
    <section aria-labelledby="effective-access-heading" data-testid={testId}>
      <h3 id="effective-access-heading" className="text-sm font-semibold text-gray-900">
        Effective access summary
      </h3>
      <p className="mt-1 text-xs text-jp-muted">
        Derived from assigned roles — {summary.totalPermissions} total permissions across {summary.domains.length} domains.
      </p>
      {summary.highRiskPermissions.length > 0 ? (
        <div className="mt-2 rounded-lg border border-red-200 bg-red-50/50 px-3 py-2 text-xs text-red-900">
          <span className="font-medium">High-risk permissions: </span>
          <span className="break-all">{summary.highRiskPermissions.join(", ")}</span>
        </div>
      ) : null}
      <div className="mt-3">
        <AccessDomainGrid domains={summary.domains} />
      </div>
      <details className="mt-3">
        <summary className="cursor-pointer text-xs font-medium text-jp-accent focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent">
          Full permission keys ({summary.totalPermissions})
        </summary>
        <ul className="mt-2 max-h-40 overflow-y-auto text-xs break-all">
          {summary.highRiskPermissions.map((key) => (
            <li key={key} className="text-red-800">{key} (high risk)</li>
          ))}
        </ul>
      </details>
    </section>
  );
}
