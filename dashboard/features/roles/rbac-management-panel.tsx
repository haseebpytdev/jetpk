"use client";

import { useMemo, useState, useTransition } from "react";
import { useDashboardRouter } from "@/lib/dashboard-navigation";
import { PERMISSION_GROUP_LABELS } from "@/lib/access-control/permission-catalog";
import {
  assignRbacRole,
  cloneRbacRole,
  createRbacRole,
  deleteRbacRole,
  unassignRbacRole,
  updateRbacRole,
} from "@/features/roles/rbac-write-api";
import type { Role } from "@/types/access-control";

type CatalogPermission = { key: string; label: string; category: string; highRisk?: boolean };

type AssignedUser = { id: string; name: string; email?: string };

type Props = {
  selectedRole: Role | null;
  permissionKeys: string[];
  assignedUsers: AssignedUser[];
  catalogPermissions: CatalogPermission[];
};

const GROUPS = [
  "dashboard",
  "bookings",
  "payments",
  "customers",
  "agents",
  "suppliers",
  "cms",
  "support",
  "users",
  "staff",
  "roles",
  "settings",
  "audit",
  "reports",
  "pnrs",
  "tickets",
] as const;

export function RbacManagementPanel({ selectedRole, permissionKeys, assignedUsers, catalogPermissions }: Props) {
  const router = useDashboardRouter();
  const [pending, startTransition] = useTransition();
  const [error, setError] = useState<string | null>(null);
  const [name, setName] = useState("QA Custom Role");
  const [agencyId, setAgencyId] = useState("");
  const [userId, setUserId] = useState("");
  const [selectedKeys, setSelectedKeys] = useState<string[]>(permissionKeys);

  const grouped = useMemo(() => {
    const map = new Map<string, CatalogPermission[]>();
    for (const permission of catalogPermissions) {
      const list = map.get(permission.category) ?? [];
      list.push(permission);
      map.set(permission.category, list);
    }
    return map;
  }, [catalogPermissions]);

  const refresh = () => startTransition(() => router.refresh());

  const run = async (fn: () => Promise<unknown>) => {
    setError(null);
    try {
      await fn();
      refresh();
    } catch (err) {
      setError(err instanceof Error ? err.message : "RBAC request failed");
    }
  };

  const toggle = (key: string) => {
    setSelectedKeys((current) => (current.includes(key) ? current.filter((item) => item !== key) : [...current, key]));
  };

  const protectedRole = Boolean(selectedRole?.isProtected || selectedRole?.isSystem);

  return (
    <section className="mt-6 rounded-xl border border-jp-border bg-white p-4" data-testid="rbac-management-panel">
      <h2 className="text-base font-semibold text-gray-900">Role management</h2>
      <p className="mt-1 text-sm text-jp-muted">
        System roles are protected. Create agency-scoped custom roles, assign catalog permissions, and attach users without
        changing AccountType or staff_permissions meta.
      </p>
      {error ? <p className="mt-2 text-sm text-red-700">{error}</p> : null}

      <div className="mt-4 grid gap-3 sm:grid-cols-2">
        <label className="text-sm">
          Role name
          <input className="mt-1 w-full rounded border px-2 py-1" value={name} onChange={(e) => setName(e.target.value)} />
        </label>
        <label className="text-sm">
          Agency ID
          <input
            className="mt-1 w-full rounded border px-2 py-1"
            value={agencyId}
            onChange={(e) => setAgencyId(e.target.value)}
            inputMode="numeric"
          />
        </label>
      </div>

      <div className="mt-4 max-h-72 overflow-auto rounded border p-3">
        {GROUPS.filter((group) => grouped.has(group)).map((group) => (
          <fieldset key={group} className="mb-3">
            <legend className="text-xs font-semibold uppercase tracking-wide text-jp-muted">
              {PERMISSION_GROUP_LABELS[group as keyof typeof PERMISSION_GROUP_LABELS] ?? group}
            </legend>
            <div className="mt-1 grid gap-1 sm:grid-cols-2">
              {(grouped.get(group) ?? []).map((permission) => (
                <label key={permission.key} className="flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={selectedKeys.includes(permission.key)}
                    onChange={() => toggle(permission.key)}
                  />
                  <span>{permission.label}</span>
                </label>
              ))}
            </div>
          </fieldset>
        ))}
      </div>

      <div className="mt-4 flex flex-wrap gap-2">
        <button
          type="button"
          className="rounded bg-gray-900 px-3 py-1.5 text-sm text-white disabled:opacity-50"
          disabled={pending}
          onClick={() =>
            run(() =>
              createRbacRole({
                name,
                agency_id: Number(agencyId),
                permission_keys: selectedKeys.filter((key) => !key.startsWith("users.assign") && !key.startsWith("roles.")),
              }),
            )
          }
        >
          Create custom role
        </button>
        {selectedRole ? (
          <>
            <button
              type="button"
              className="rounded border px-3 py-1.5 text-sm disabled:opacity-50"
              disabled={pending || !agencyId}
              onClick={() =>
                run(() =>
                  cloneRbacRole(selectedRole.id, {
                    name: `${selectedRole.name} copy`,
                    agency_id: Number(agencyId),
                  }),
                )
              }
            >
              Clone role
            </button>
            <button
              type="button"
              className="rounded border px-3 py-1.5 text-sm disabled:opacity-50"
              disabled={pending || protectedRole}
              onClick={() => run(() => updateRbacRole(selectedRole.id, { permission_keys: selectedKeys }))}
            >
              Save permissions
            </button>
            <button
              type="button"
              className="rounded border border-red-300 px-3 py-1.5 text-sm text-red-800 disabled:opacity-50"
              disabled={pending || protectedRole}
              onClick={() => run(() => deleteRbacRole(selectedRole.id))}
            >
              Delete custom role
            </button>
          </>
        ) : null}
      </div>

      {selectedRole ? (
        <div className="mt-4 grid gap-2 sm:grid-cols-2">
          <label className="text-sm">
            Assign user ID
            <input className="mt-1 w-full rounded border px-2 py-1" value={userId} onChange={(e) => setUserId(e.target.value)} />
          </label>
          <div className="flex items-end gap-2">
            <button
              type="button"
              className="rounded border px-3 py-1.5 text-sm"
              disabled={pending || !userId}
              onClick={() => run(() => assignRbacRole(selectedRole.id, Number(userId)))}
            >
              Assign
            </button>
            <button
              type="button"
              className="rounded border px-3 py-1.5 text-sm"
              disabled={pending || !userId}
              onClick={() => run(() => unassignRbacRole(selectedRole.id, Number(userId)))}
            >
              Remove assignment
            </button>
          </div>
          <p className="sm:col-span-2 text-sm text-jp-muted">
            Assigned: {assignedUsers.length === 0 ? "none" : assignedUsers.map((user) => `${user.name} (#${user.id})`).join(", ")}
          </p>
        </div>
      ) : null}
    </section>
  );
}
