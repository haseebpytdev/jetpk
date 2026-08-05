import type { User } from "@/types/access-control";

type Indicator = { label: string; active: boolean; severity: "info" | "warning" | "error" };

function buildIndicators(user: User): Indicator[] {
  return [
    { label: "MFA enabled", active: user.security.mfaState === "enabled", severity: "info" },
    { label: "Verified email", active: user.security.verificationState === "verified", severity: "info" },
    { label: "Locked account", active: user.security.status === "locked", severity: "error" },
    { label: "Failed sign-ins elevated", active: user.security.failedSignInCount >= 5, severity: "warning" },
    { label: "Multiple active sessions", active: user.session.activeSessionCount > 1, severity: "warning" },
    { label: "Stale invitation", active: user.security.invitationState === "pending" && user.security.status === "invited", severity: "warning" },
    { label: "Suspended account", active: user.security.status === "suspended", severity: "error" },
    { label: "No assigned role", active: user.assignedRoles.length === 0, severity: "warning" },
    { label: "High-risk permission access", active: user.effectiveAccess.highRiskPermissions.length > 0, severity: "warning" },
    { label: "Review required", active: user.validationState === "review" || user.validationState === "warning" || user.validationState === "blocked", severity: "warning" },
  ];
}

const toneMap = {
  info: "bg-blue-50 text-blue-900 ring-blue-600/20",
  warning: "bg-amber-50 text-amber-900 ring-amber-600/20",
  error: "bg-red-50 text-red-800 ring-red-600/20",
};

export function UserSecuritySummary({ user }: { user: User }) {
  const indicators = buildIndicators(user);
  const active = indicators.filter((i) => i.active);

  return (
    <section aria-labelledby="user-security-summary-heading" data-testid="user-security-summary">
      <h3 id="user-security-summary-heading" className="text-sm font-semibold text-gray-900">
        Security summary
      </h3>
      <p className="mt-1 text-xs text-jp-muted">Read-only indicators — no operational controls.</p>
      {active.length === 0 ? (
        <p className="mt-2 text-sm text-jp-muted">No security warnings detected.</p>
      ) : (
        <ul className="mt-2 flex flex-wrap gap-2" role="list">
          {active.map((indicator, index) => (
            <li key={`${index}-${indicator.label}`}>
              <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${toneMap[indicator.severity]}`}>
                {indicator.label}
              </span>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
