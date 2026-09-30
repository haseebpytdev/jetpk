"use client";

import { useCallback, useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import {
  adminUserEditPath,
  adminUserUpdatePath,
  staffManagementPath,
} from "@/lib/api/portal-paths";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";

type StaffRow = {
  id: number;
  user_id?: number;
  name?: string;
  email?: string;
  job_title?: string;
  status?: string;
};

type EditPayload = {
  ok?: boolean;
  user?: {
    id?: string;
    name?: string;
    email?: string;
    account_type?: string;
    status?: string;
  };
  isStaffPermissions?: boolean;
  selectedStaffPermissions?: string[];
  groupedStaffPermissions?: Record<string, Record<string, string>>;
  staffPresetLabels?: Record<string, string>;
  staffPresetPermissions?: Record<string, string[]>;
  groupedEffectiveAccess?: Record<
    string,
    Array<{ area: string; access: string; enabled: boolean; limited: boolean }>
  >;
};

function payloadOf<T extends Record<string, unknown>>(result: { ok: boolean; data?: T } | T): T {
  if (result && typeof result === "object" && "data" in result && result.data) {
    return result.data as T;
  }
  return result as T;
}

/**
 * CURRENT-domain RBAC write surface: AccountType system roles stay catalog/read,
 * StaffPermission writes go through UserManagement JSON (users.meta.staff_permissions).
 * Custom Role CRUD tables are not present on HEAD — not invented here.
 */
export function StaffRbacOperationalPanel() {
  const isLive = useDashboardLiveMode();
  const [staff, setStaff] = useState<StaffRow[]>([]);
  const [search, setSearch] = useState("");
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [catalog, setCatalog] = useState<EditPayload | null>(null);
  const [selectedPerms, setSelectedPerms] = useState<string[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const refreshStaff = useCallback(async () => {
    const params = new URLSearchParams();
    if (search) params.set("search", search);
    const result = await laravelRequest(staffManagementPath(params.toString()), {
      method: "GET",
      retryCsrfOnce: false,
    });
    if (!result.ok) {
      setError(result.message ?? "Could not load staff for RBAC assignment.");
      return;
    }
    const payload = payloadOf(result);
    setStaff(Array.isArray(payload.staff) ? (payload.staff as StaffRow[]) : []);
  }, [search]);

  const loadStaff = useCallback(async (userId: number) => {
    setSelectedId(userId);
    setError(null);
    setSuccess(null);
    const result = await laravelRequest<EditPayload>(adminUserEditPath(userId), {
      method: "GET",
      headers: { Accept: "application/json" },
      retryCsrfOnce: false,
    });
    if (!result.ok) {
      setError(result.message ?? "Could not load staff permissions.");
      setCatalog(null);
      return;
    }
    setCatalog(result.data);
    setSelectedPerms(
      Array.isArray(result.data.selectedStaffPermissions)
        ? [...result.data.selectedStaffPermissions]
        : [],
    );
  }, []);

  useEffect(() => {
    if (!isLive) return;
    void refreshStaff();
  }, [isLive, refreshStaff]);

  if (!isLive) {
    return null;
  }

  async function onSave() {
    if (!selectedId || !catalog?.user || saving) return;
    setSaving(true);
    setError(null);
    setSuccess(null);
    const result = await laravelRequest(adminUserUpdatePath(selectedId), {
      method: "PATCH",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      json: {
        name: catalog.user.name,
        email: catalog.user.email,
        account_type: "staff",
        status: catalog.user.status,
        staff_permissions_configured: true,
        staff_permissions: selectedPerms,
      },
      retryCsrfOnce: true,
    });
    setSaving(false);
    if (!result.ok) {
      setError(result.message ?? "Could not save staff permissions.");
      return;
    }
    setSuccess("Staff permissions saved.");
    await loadStaff(selectedId);
  }

  const groups = catalog?.groupedStaffPermissions ?? {};
  const presets = catalog?.staffPresetLabels ?? {};
  const effective = catalog?.groupedEffectiveAccess ?? {};

  return (
    <section
      className="mt-6 space-y-4 rounded-xl border border-jp-border p-4"
      data-testid="staff-rbac-operational-panel"
    >
      <div>
        <h2 className="text-base font-semibold text-gray-900">Operational staff RBAC</h2>
        <p className="mt-1 text-sm text-jp-muted">
          System account-type roles remain protected catalog entries. Granular writes use current{" "}
          <code className="text-xs">StaffPermission</code> storage on staff users. Custom Role /
          role_user tables are not part of the current domain.
        </p>
      </div>

      <div className="flex flex-wrap gap-2">
        <Input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search staff"
          className="max-w-xs"
          data-testid="rbac-staff-search"
        />
        <Button type="button" size="sm" onClick={() => void refreshStaff()}>
          Search
        </Button>
      </div>

      {error ? (
        <p className="text-sm text-red-700" role="alert" data-testid="rbac-error">
          {error}
        </p>
      ) : null}
      {success ? (
        <p className="text-sm text-emerald-700" data-testid="rbac-success">
          {success}
        </p>
      ) : null}

      <div className="overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead>
            <tr className="border-b text-jp-muted">
              <th className="px-2 py-2">Name</th>
              <th className="px-2 py-2">Role title</th>
              <th className="px-2 py-2">Status</th>
              <th className="px-2 py-2" />
            </tr>
          </thead>
          <tbody>
            {staff.map((row) => {
              const uid = Number(row.user_id ?? row.id);
              return (
                <tr key={uid} className="border-b last:border-0">
                  <td className="px-2 py-2">
                    {row.name}
                    <div className="text-xs text-jp-muted">{row.email}</div>
                  </td>
                  <td className="px-2 py-2">{row.job_title}</td>
                  <td className="px-2 py-2">{row.status}</td>
                  <td className="px-2 py-2 text-right">
                    <Button
                      type="button"
                      size="sm"
                      variant={selectedId === uid ? "primary" : "secondary"}
                      onClick={() => void loadStaff(uid)}
                      data-testid={`rbac-select-staff-${uid}`}
                    >
                      Assign permissions
                    </Button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {catalog?.isStaffPermissions && selectedId ? (
        <div className="space-y-3" data-testid="rbac-permission-editor">
          <h3 className="text-sm font-semibold">
            Permissions for {catalog.user?.name ?? `user #${selectedId}`}
          </h3>
          {Object.keys(presets).length > 0 ? (
            <div className="flex flex-wrap gap-2">
              {Object.entries(presets).map(([key, label]) => (
                <Button
                  key={key}
                  type="button"
                  size="sm"
                  variant="secondary"
                  onClick={() => {
                    const perms = catalog.staffPresetPermissions?.[key];
                    if (Array.isArray(perms)) setSelectedPerms([...perms]);
                  }}
                >
                  {label}
                </Button>
              ))}
            </div>
          ) : null}
          {Object.entries(groups).map(([groupName, items]) => (
            <div key={groupName}>
              <p className="text-xs font-medium text-jp-muted">{groupName}</p>
              <div className="mt-1 grid gap-1 sm:grid-cols-2">
                {Object.entries(items).map(([key, label]) => (
                  <label key={key} className="flex items-center gap-2 text-sm">
                    <input
                      type="checkbox"
                      checked={selectedPerms.includes(key)}
                      onChange={() =>
                        setSelectedPerms((prev) =>
                          prev.includes(key) ? prev.filter((k) => k !== key) : [...prev, key],
                        )
                      }
                      data-testid={`rbac-perm-${key}`}
                    />
                    <span>{label}</span>
                  </label>
                ))}
              </div>
            </div>
          ))}
          {Object.keys(effective).length > 0 ? (
            <div data-testid="rbac-effective-access">
              <p className="text-xs font-medium text-jp-muted">Effective access preview</p>
              <ul className="mt-1 list-disc pl-5 text-xs text-gray-700">
                {Object.entries(effective).flatMap(([groupName, items]) =>
                  items.map((item) => (
                    <li key={`${groupName}-${item.area}`}>
                      {groupName} / {item.area}: {item.access}
                    </li>
                  )),
                )}
              </ul>
            </div>
          ) : null}
          <Button
            type="button"
            size="sm"
            onClick={() => void onSave()}
            disabled={saving}
            data-testid="rbac-save-permissions"
          >
            {saving ? "Saving…" : "Save staff permissions"}
          </Button>
        </div>
      ) : null}
    </section>
  );
}
