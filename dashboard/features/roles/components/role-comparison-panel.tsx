import { compareRoles, formatRoleComparisonNote } from "@/lib/access-control/role-comparison";
import { PERMISSION_BY_KEY } from "@/lib/access-control/permission-catalog";

type Props = {
  compareA: string | null;
  compareB: string | null;
};

export function RoleComparisonPanel({ compareA, compareB }: Props) {
  if (!compareA || !compareB) {
    return (
      <section aria-labelledby="role-comparison-heading" data-testid="role-comparison-panel">
        <h3 id="role-comparison-heading" className="text-sm font-semibold text-gray-900">
          Role comparison
        </h3>
        <p className="mt-1 text-xs text-jp-muted">
          Add <code className="text-xs">compareA</code> and <code className="text-xs">compareB</code> query parameters to compare two roles.
        </p>
      </section>
    );
  }

  const result = compareRoles(compareA, compareB);

  if (!result) {
    return (
      <section aria-labelledby="role-comparison-heading" data-testid="role-comparison-panel">
        <h3 id="role-comparison-heading" className="text-sm font-semibold text-gray-900">
          Role comparison
        </h3>
        <p className="mt-1 text-sm text-jp-muted">One or both role IDs were not found in fixtures.</p>
      </section>
    );
  }

  const metrics: { label: string; a: number | string; b: number | string }[] = [
    { label: "Permission count", a: result.permissionCountA, b: result.permissionCountB },
    { label: "Domain coverage", a: result.domainCoverageA, b: result.domainCoverageB },
    { label: "View access", a: result.viewAccessA, b: result.viewAccessB },
    { label: "Request access", a: result.requestAccessA, b: result.requestAccessB },
    { label: "Approval access", a: result.approvalAccessA, b: result.approvalAccessB },
    { label: "Manage access", a: result.manageAccessA, b: result.manageAccessB },
    { label: "Export access", a: result.exportAccessA, b: result.exportAccessB },
  ];

  return (
    <section aria-labelledby="role-comparison-heading" data-testid="role-comparison-panel">
      <h3 id="role-comparison-heading" className="text-sm font-semibold text-gray-900">
        Role comparison
      </h3>
      <p className="mt-1 text-xs text-jp-muted">{formatRoleComparisonNote(result)}</p>

      <div className="mt-3 grid gap-3 sm:grid-cols-2">
        <div className="rounded-xl border border-jp-border p-3">
          <h4 className="text-sm font-semibold">{result.roleA.name}</h4>
          <p className="text-xs text-jp-muted">{result.roleA.id}</p>
        </div>
        <div className="rounded-xl border border-jp-border p-3">
          <h4 className="text-sm font-semibold">{result.roleB.name}</h4>
          <p className="text-xs text-jp-muted">{result.roleB.id}</p>
        </div>
      </div>

      <dl className="mt-3 grid gap-2 text-sm sm:grid-cols-3">
        <div className="font-medium text-jp-muted">Metric</div>
        <div className="font-medium">{result.roleA.name}</div>
        <div className="font-medium">{result.roleB.name}</div>
        {metrics.map((m) => (
          <div key={m.label} className="contents">
            <div className="text-jp-muted">{m.label}</div>
            <div className="tabular-nums">{m.a}</div>
            <div className="tabular-nums">{m.b}</div>
          </div>
        ))}
      </dl>

      <div className="mt-4 grid gap-3 sm:grid-cols-3">
        <div>
          <h4 className="text-xs font-semibold text-gray-900">Unique to {result.roleA.name}</h4>
          <ul className="mt-1 max-h-32 overflow-y-auto text-xs break-all">
            {result.uniqueToA.length > 0 ? result.uniqueToA.map((k) => (
              <li key={k}>{PERMISSION_BY_KEY.get(k)?.label ?? k}</li>
            )) : <li className="text-jp-muted">None</li>}
          </ul>
        </div>
        <div>
          <h4 className="text-xs font-semibold text-gray-900">Shared</h4>
          <ul className="mt-1 max-h-32 overflow-y-auto text-xs break-all">
            {result.shared.length > 0 ? result.shared.map((k) => (
              <li key={k}>{PERMISSION_BY_KEY.get(k)?.label ?? k}</li>
            )) : <li className="text-jp-muted">None</li>}
          </ul>
        </div>
        <div>
          <h4 className="text-xs font-semibold text-gray-900">Unique to {result.roleB.name}</h4>
          <ul className="mt-1 max-h-32 overflow-y-auto text-xs break-all">
            {result.uniqueToB.length > 0 ? result.uniqueToB.map((k) => (
              <li key={k}>{PERMISSION_BY_KEY.get(k)?.label ?? k}</li>
            )) : <li className="text-jp-muted">None</li>}
          </ul>
        </div>
      </div>
    </section>
  );
}
