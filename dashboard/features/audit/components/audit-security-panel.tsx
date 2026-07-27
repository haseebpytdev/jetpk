"use client";

import { Button } from "@/components/ui/button";

type Props = {
  active: boolean;
  securityEventCount: number;
  onToggle: () => void;
};

export function AuditSecurityPanel({ active, securityEventCount, onToggle }: Props) {
  return (
    <div
      className="rounded-2xl border border-jp-border bg-white px-4 py-3"
      data-testid="audit-security-panel"
      role="region"
      aria-labelledby="audit-security-panel-heading"
    >
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 id="audit-security-panel-heading" className="text-sm font-semibold text-gray-900">
            Security event view
          </h2>
          <p className="mt-1 text-xs text-jp-muted">
            Informational preview of failed sign-ins, policy warnings, denied authorization, and related security events.
            {securityEventCount > 0 ? ` ${securityEventCount} security-related events in fixtures.` : ""}
          </p>
        </div>
        <Button
          type="button"
          size="sm"
          variant={active ? "primary" : "secondary"}
          onClick={onToggle}
          aria-pressed={active}
        >
          {active ? "Showing security events" : "Show security events"}
        </Button>
      </div>
      <p className="mt-2 text-xs text-jp-muted">
        No unlock, revoke, reset, suspend, or delete actions are available in preview.
      </p>
    </div>
  );
}
