import type { AccessDecision } from "@/types/access-control";
import type { AuditEvent } from "@/types/access-control";
import { AccessRiskBadge } from "@/components/ui/status-badge";

type Props = {
  event: AuditEvent;
  decision: AccessDecision | null;
};

export function AuditAuthorizationSummary({ event, decision }: Props) {
  return (
    <section aria-labelledby="audit-authorization-heading" data-testid="audit-authorization-summary">
      <h3 id="audit-authorization-heading" className="text-sm font-semibold text-gray-900">Authorization explanation (preview)</h3>
      <p className="mt-1 text-xs text-jp-muted">
        Fixture-based access decision preview — not live Laravel enforcement.
      </p>
      <dl className="mt-3 grid gap-2 text-sm">
        <div className="flex justify-between gap-4"><dt className="text-jp-muted">Authorization outcome</dt><dd>{event.authorizationOutcome}</dd></div>
        {event.permissionKey ? <div className="flex justify-between gap-4"><dt className="text-jp-muted">Permission key</dt><dd className="break-all">{event.permissionKey}</dd></div> : null}
        {decision?.sourceRoleId ? <div className="flex justify-between gap-4"><dt className="text-jp-muted">Source role</dt><dd>{decision.sourceRoleId}</dd></div> : null}
        <div className="flex justify-between gap-4"><dt className="text-jp-muted">Resource</dt><dd>{event.target.type}</dd></div>
        <div className="flex justify-between gap-4"><dt className="text-jp-muted">Scope</dt><dd>{decision?.scope ?? event.metadata.scope ?? "—"}</dd></div>
        <div className="flex justify-between gap-4"><dt className="text-jp-muted">Channel</dt><dd>{event.metadata.channel ?? "—"}</dd></div>
        {decision ? <div className="flex justify-between gap-4"><dt className="text-jp-muted">Reason</dt><dd>{decision.reason}</dd></div> : null}
        <div className="flex flex-wrap items-center gap-2">
          <dt className="text-jp-muted">High-risk indicator</dt>
          <dd><AccessRiskBadge highRisk={(decision?.highRisk ?? false) || event.riskState === "high" || event.riskState === "critical"} /></dd>
        </div>
      </dl>
    </section>
  );
}
