"use client";

import { DashboardLink as Link } from "@/components/dashboard/dashboard-link";
import { Divider } from "@/components/ui/divider";
import { PreviewDataBanner } from "@/components/ui/page-layout";
import { AccessRiskBadge, AccessValidationBadge } from "@/components/ui/status-badge";
import { evaluateAccessDecision } from "@/lib/access-control/access-decision";
import { getAuditActorHref, getAuditTargetHref } from "@/lib/audit/target-links";
import { formatDate } from "@/lib/format";
import { getUserById } from "@/mocks/user-fixtures";
import type { AuditEvent } from "@/types/access-control";
import { AuditAuthorizationSummary } from "@/features/audit/components/audit-authorization-summary";
import { AuditOutcomeBadge, AuditSeverityBadge } from "@/features/audit/components/audit-status-badges";

export function AuditEventDetailDrawer({ event }: { event: AuditEvent }) {
  const actorHref = getAuditActorHref(event.actor);
  const targetHref = getAuditTargetHref(event.target);
  const user = event.actor.userId ? getUserById(event.actor.userId) : null;
  const authDecision =
    event.permissionKey && user
      ? evaluateAccessDecision({
          user,
          roleIds: user.assignedRoles.map((r) => r.roleId),
          permissionKey: event.permissionKey,
          channelContext: event.metadata.channel === "gds" ? "gds" : event.metadata.channel === "ndc" ? "ndc" : null,
        })
      : null;

  return (
    <div className="space-y-5" data-testid="audit-event-detail-drawer">
      <PreviewDataBanner className="text-xs" />

      {event.metadata.previewOnly ? (
        <p className="rounded-xl border border-violet-200 bg-violet-50 px-3 py-2 text-sm text-violet-900" role="status">
          Preview-only event — no live mutation was recorded.
        </p>
      ) : null}

      <section aria-labelledby="audit-identity-heading">
        <h3 id="audit-identity-heading" className="text-sm font-semibold text-gray-900">Event identity</h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Event ID</dt><dd className="font-medium">{event.id}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Timestamp</dt><dd>{formatDate(event.occurredAt)}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Event type</dt><dd>{event.type}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Category</dt><dd>{event.category}</dd></div>
          <div className="flex flex-wrap items-center gap-2">
            <dt className="text-jp-muted">Severity</dt>
            <dd><AuditSeverityBadge severity={event.severity} /></dd>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <dt className="text-jp-muted">Outcome</dt>
            <dd><AuditOutcomeBadge outcome={event.outcome} /></dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="audit-actor-heading">
        <h3 id="audit-actor-heading" className="text-sm font-semibold text-gray-900">Actor summary</h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Name</dt>
            <dd>
              {actorHref ? (
                <Link href={actorHref} className="text-jp-accent hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent">
                  {event.actor.displayName}
                </Link>
              ) : (
                event.actor.displayName
              )}
            </dd>
          </div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Actor type</dt><dd>{event.actor.actorType}</dd></div>
          {event.actor.roleLabel ? <div className="flex justify-between gap-4"><dt className="text-jp-muted">Role context</dt><dd>{event.actor.roleLabel}</dd></div> : null}
          {event.actor.department ? <div className="flex justify-between gap-4"><dt className="text-jp-muted">Department</dt><dd>{event.actor.department}</dd></div> : null}
          {event.actor.status ? <div className="flex justify-between gap-4"><dt className="text-jp-muted">Status</dt><dd>{event.actor.status}</dd></div> : null}
          <div className="flex flex-wrap items-center gap-2">
            <dt className="text-jp-muted">High-risk access</dt>
            <dd><AccessRiskBadge highRisk={event.actor.highRiskAccess} /></dd>
          </div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="audit-target-heading">
        <h3 id="audit-target-heading" className="text-sm font-semibold text-gray-900">Target summary</h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-jp-muted">Target</dt>
            <dd>
              {targetHref ? (
                <Link href={targetHref} className="text-jp-accent hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent">
                  {event.target.label}
                </Link>
              ) : (
                event.target.label
              )}
            </dd>
          </div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Target type</dt><dd>{event.target.type}</dd></div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="audit-context-heading">
        <h3 id="audit-context-heading" className="text-sm font-semibold text-gray-900">Context</h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Action</dt><dd>{event.actionLabel}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Source module</dt><dd>{event.sourceModule}</dd></div>
          {event.metadata.route ? <div className="flex justify-between gap-4"><dt className="text-jp-muted">Route</dt><dd className="break-all">{event.metadata.route}</dd></div> : null}
          {event.permissionKey ? <div className="flex justify-between gap-4"><dt className="text-jp-muted">Permission key</dt><dd className="break-all">{event.permissionKey}</dd></div> : null}
          {event.roleContext ? <div className="flex justify-between gap-4"><dt className="text-jp-muted">Role context</dt><dd>{event.roleContext}</dd></div> : null}
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Scope</dt><dd>{event.metadata.scope ?? "—"}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Channel</dt><dd>{event.metadata.channel ?? "—"}</dd></div>
          <div className="flex flex-wrap items-center gap-2">
            <dt className="text-jp-muted">Risk state</dt>
            <dd><AccessRiskBadge highRisk={event.riskState === "high" || event.riskState === "critical"} label={event.riskState} /></dd>
          </div>
        </dl>
      </section>

      {event.permissionKey ? (
        <>
          <Divider />
          <AuditAuthorizationSummary event={event} decision={authDecision} />
        </>
      ) : null}

      <Divider />

      <section aria-labelledby="audit-network-heading">
        <h3 id="audit-network-heading" className="text-sm font-semibold text-gray-900">Masked network metadata</h3>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Masked IP</dt><dd>{event.metadata.maskedIp ?? "—"}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Network range</dt><dd>{event.metadata.maskedNetworkRange ?? "—"}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">User-agent summary</dt><dd>{event.metadata.userAgentSummary ?? "—"}</dd></div>
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="audit-reference-heading">
        <h3 id="audit-reference-heading" className="text-sm font-semibold text-gray-900">Correlation and change</h3>
        <dl className="mt-2 grid gap-2 text-sm">
          {event.metadata.correlationId ? <div className="flex justify-between gap-4"><dt className="text-jp-muted">Correlation ID</dt><dd>{event.metadata.correlationId}</dd></div> : null}
          {event.metadata.referenceLabel ? <div className="flex justify-between gap-4"><dt className="text-jp-muted">Reference</dt><dd>{event.metadata.referenceLabel}</dd></div> : null}
          <div><dt className="text-jp-muted">Summary</dt><dd className="mt-1">{event.summary}</dd></div>
          {event.changeSummary ? <div><dt className="text-jp-muted">Change summary</dt><dd className="mt-1">{event.changeSummary}</dd></div> : null}
        </dl>
      </section>

      <Divider />

      <section aria-labelledby="audit-validation-heading">
        <h3 id="audit-validation-heading" className="text-sm font-semibold text-gray-900">Validation and retention</h3>
        <div className="mt-2">
          <AccessValidationBadge status={event.validationState} />
        </div>
        <dl className="mt-2 grid gap-2 text-sm">
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Retention category</dt><dd>{event.metadata.retentionCategory}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-jp-muted">Synthetic source</dt><dd>{event.metadata.syntheticSource}</dd></div>
        </dl>
      </section>

      <p className="text-xs text-jp-muted" data-testid="audit-laravel-boundary-note">
        Future Laravel audit integration will source authoritative events from the application audit log. This drawer shows fixture-backed preview metadata only.
      </p>
    </div>
  );
}
