import { AccessRiskBadge, AccessValidationBadge } from "@/components/ui/status-badge";
import { formatDate } from "@/lib/format";
import type { AuditTableRow } from "@/types/audit";
import { AuditOutcomeBadge, AuditSeverityBadge } from "@/features/audit/components/audit-status-badges";

type Props = {
  rows: AuditTableRow[];
  onView: (id: string) => void;
};

export function AuditMobileCard({ rows, onView }: Props) {
  return (
    <div className="space-y-3 md:hidden" data-testid="audit-mobile-cards">
      {rows.map((row) => (
        <article key={row.id} className="rounded-2xl border border-jp-border bg-white p-4">
          <button
            type="button"
            className="text-left w-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
            onClick={() => onView(row.id)}
            aria-label={`View audit event ${row.id}`}
          >
            <div className="flex flex-wrap items-start justify-between gap-2">
              <span className="font-medium text-jp-accent">{row.id}</span>
              <span className="text-xs text-jp-muted">{formatDate(row.occurredAt)}</span>
            </div>
            <p className="mt-2 font-medium">{row.eventLabel}</p>
            <dl className="mt-3 grid grid-cols-2 gap-2 text-xs">
              <div><dt className="text-jp-muted">Category</dt><dd>{row.category}</dd></div>
              <div><dt className="text-jp-muted">Actor</dt><dd>{row.actorName}</dd></div>
              <div><dt className="text-jp-muted">Target</dt><dd className="truncate">{row.targetLabel}</dd></div>
              <div><dt className="text-jp-muted">Module</dt><dd>{row.sourceModule}</dd></div>
            </dl>
            <div className="mt-3 flex flex-wrap gap-2">
              <AuditSeverityBadge severity={row.severity} />
              <AuditOutcomeBadge outcome={row.outcome} />
              <AccessRiskBadge highRisk={row.riskState === "high" || row.riskState === "critical"} label={row.riskState} />
              <AccessValidationBadge status={row.validationState} />
            </div>
          </button>
        </article>
      ))}
    </div>
  );
}
