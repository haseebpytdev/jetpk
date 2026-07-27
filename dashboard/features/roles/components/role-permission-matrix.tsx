"use client";

import { useMemo } from "react";
import { Label } from "@/components/ui/page-layout";
import { Select } from "@/components/ui/select";
import { PERMISSION_CATALOG, PERMISSION_GROUP_LABELS } from "@/lib/access-control/permission-catalog";
import { getRolePermissionKeys } from "@/lib/roles/query-filters";
import { mockRoles } from "@/mocks/rbac-fixtures";
import type { PermissionGroup } from "@/types/access-control";
import type { RoleTableRow } from "@/types/roles";

type Props = {
  rows: RoleTableRow[];
  matrixDomain: string;
  matrixRole: string;
  onDomainChange: (domain: string) => void;
  onRoleChange: (roleId: string) => void;
};

const DOMAIN_ORDER: PermissionGroup[] = [
  "dashboard",
  "bookings",
  "payments",
  "customers",
  "suppliers",
  "agents",
  "pnrs",
  "tickets",
  "reports",
  "cms",
  "users",
  "roles",
  "settings",
  "audit",
];

function roleHasPermission(roleId: string, permissionKey: string): boolean {
  return getRolePermissionKeys(roleId).includes(permissionKey);
}

export function RolePermissionMatrix({
  rows,
  matrixDomain,
  matrixRole,
  onDomainChange,
  onRoleChange,
}: Props) {
  const displayRoles = rows.slice(0, 6);
  const domains = matrixDomain
    ? DOMAIN_ORDER.filter((d) => d === matrixDomain)
    : DOMAIN_ORDER;

  const matrixPermissions = useMemo(
    () =>
      PERMISSION_CATALOG.filter((p) =>
        matrixDomain ? p.domain === matrixDomain : true,
      ),
    [matrixDomain],
  );

  const selectedRolePermissions = useMemo(() => {
    const roleId = matrixRole || displayRoles[0]?.id;
    if (!roleId) return [];
    return getRolePermissionKeys(roleId)
      .map((key) => PERMISSION_CATALOG.find((p) => p.key === key))
      .filter(Boolean);
  }, [matrixRole, displayRoles]);

  return (
    <section aria-labelledby="role-matrix-heading" data-testid="role-permission-matrix" className="mt-6">
      <h2 id="role-matrix-heading" className="text-sm font-semibold text-gray-900">
        Permission matrix
      </h2>
      <p className="mt-1 text-xs text-jp-muted">
        Read-only matrix of fixture role permissions by domain. No mutations are persisted.
      </p>

      <div className="mt-3 grid gap-4 sm:grid-cols-2">
        <div>
          <Label htmlFor="matrix-domain-filter">Domain filter</Label>
          <Select
            id="matrix-domain-filter"
            value={matrixDomain}
            onChange={(e) => onDomainChange(e.target.value)}
          >
            <option value="">All domains</option>
            {DOMAIN_ORDER.map((d) => (
              <option key={d} value={d}>{PERMISSION_GROUP_LABELS[d]}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="matrix-role-filter">Role filter (mobile)</Label>
          <Select
            id="matrix-role-filter"
            value={matrixRole}
            onChange={(e) => onRoleChange(e.target.value)}
          >
            <option value="">First visible role</option>
            {mockRoles.map((r) => (
              <option key={r.id} value={r.id}>{r.name}</option>
            ))}
          </Select>
        </div>
      </div>

      <div className="mt-4 hidden overflow-x-auto md:block">
        <table className="min-w-full border-collapse text-xs">
          <thead>
            <tr>
              <th className="sticky left-0 bg-white px-2 py-2 text-left font-semibold">Domain / Permission</th>
              {displayRoles.map((role) => (
                <th key={role.id} className="px-2 py-2 text-left font-semibold whitespace-nowrap">
                  {role.name}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {domains.map((domain) => {
              const domainPerms = matrixPermissions.filter((p) => p.domain === domain);
              if (domainPerms.length === 0) return null;
              return domainPerms.map((perm, permIndex) => (
                <tr key={perm.key} className="border-t border-jp-border">
                  <td className="sticky left-0 bg-white px-2 py-2">
                    {permIndex === 0 ? (
                      <span className="block font-semibold text-gray-900">{PERMISSION_GROUP_LABELS[domain]}</span>
                    ) : null}
                    {perm.label}
                  </td>
                  {displayRoles.map((role) => {
                    const granted = roleHasPermission(role.id, perm.key);
                    return (
                      <td key={`${role.id}-${perm.key}`} className="px-2 py-2 text-center">
                        <span
                          className={granted ? "text-emerald-700" : "text-jp-muted"}
                          aria-label={`${role.name}: ${perm.label} — ${granted ? "granted" : "not granted"}`}
                        >
                          {granted ? "●" : "—"}
                        </span>
                      </td>
                    );
                  })}
                </tr>
              ));
            })}
          </tbody>
        </table>
      </div>

      <div className="mt-4 md:hidden">
        <h3 className="text-xs font-semibold text-gray-900">
          {matrixRole
            ? mockRoles.find((r) => r.id === matrixRole)?.name ?? matrixRole
            : displayRoles[0]?.name ?? "Role"} permissions
        </h3>
        <ul className="mt-2 max-h-64 space-y-1 overflow-y-auto text-xs">
          {selectedRolePermissions.length > 0 ? (
            selectedRolePermissions.map((perm) => (
              <li key={perm!.key} className="rounded-lg border border-jp-border px-3 py-2">
                <span className="font-medium">{perm!.label}</span>
                <span className="ml-2 text-jp-muted">{PERMISSION_GROUP_LABELS[perm!.domain]}</span>
              </li>
            ))
          ) : (
            <li className="text-jp-muted">No permissions for selected role.</li>
          )}
        </ul>
      </div>
    </section>
  );
}
