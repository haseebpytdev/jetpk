"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { Card, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import {
  adminUserCreatePath,
  adminUserEditPath,
  adminUserStorePath,
  adminUserUpdatePath,
  staffManagementPath,
  userActivatePath,
  userSuspendPath,
} from "@/lib/api/portal-paths";
import { emptyListDescription } from "@/lib/empty-list-copy";

type StaffRow = {
  id: number;
  user_id?: number;
  staff_code?: string;
  name?: string;
  email?: string;
  phone?: string | null;
  job_title?: string;
  department?: string;
  status?: string;
  assigned_bookings?: number;
  staff_permissions?: string[];
};

type StaffEditCatalog = {
  ok?: boolean;
  user?: {
    id?: string;
    name?: string;
    email?: string;
    account_type?: string;
    status?: string;
    phone?: string | null;
  };
  staffProfile?: {
    job_title?: string | null;
    department?: string | null;
  } | null;
  selectedStaffPermissions?: string[];
  groupedStaffPermissions?: Record<string, Record<string, string>>;
  staffPresetLabels?: Record<string, string>;
  staffPresetPermissions?: Record<string, string[]>;
  groupedEffectiveAccess?: Record<
    string,
    Array<{ area: string; access: string; enabled: boolean; limited: boolean }>
  >;
  isStaffPermissions?: boolean;
};

type CreateForm = {
  name: string;
  email: string;
  phone: string;
  role_title: string;
  department: string;
  status: string;
  send_invite: boolean;
};

const emptyCreateForm = (): CreateForm => ({
  name: "",
  email: "",
  phone: "",
  role_title: "Staff",
  department: "Operations",
  status: "active",
  send_invite: true,
});

function payloadOf<T extends Record<string, unknown>>(result: { ok: boolean; data?: T } | T): T {
  if (result && typeof result === "object" && "data" in result && result.data) {
    return result.data as T;
  }
  return result as T;
}

function userIdOf(row: StaffRow): number {
  return Number(row.user_id ?? row.id);
}

export function StaffManagementWorkspace() {
  const isLive = useDashboardLiveMode();
  const [rows, setRows] = useState<StaffRow[]>([]);
  const [kpis, setKpis] = useState<Record<string, unknown>>({});
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [search, setSearch] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [showCreate, setShowCreate] = useState(false);
  const [createForm, setCreateForm] = useState<CreateForm>(emptyCreateForm);
  const [creating, setCreating] = useState(false);
  const [editCatalog, setEditCatalog] = useState<StaffEditCatalog | null>(null);
  const [editLoading, setEditLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [statusBusy, setStatusBusy] = useState(false);
  const [editName, setEditName] = useState("");
  const [editEmail, setEditEmail] = useState("");
  const [editPhone, setEditPhone] = useState("");
  const [editJobTitle, setEditJobTitle] = useState("");
  const [editDepartment, setEditDepartment] = useState("");
  const [editStatus, setEditStatus] = useState("active");
  const [selectedPerms, setSelectedPerms] = useState<string[]>([]);

  const selected = useMemo(
    () => rows.find((row) => userIdOf(row) === selectedId) ?? null,
    [rows, selectedId],
  );

  const refresh = useCallback(async () => {
    const params = new URLSearchParams();
    if (search) params.set("search", search);
    const result = await laravelRequest(staffManagementPath(params.toString()), {
      method: "GET",
      retryCsrfOnce: false,
    });
    if (!result.ok) {
      setError(result.message ?? "Could not load staff.");
      return;
    }
    const payload = payloadOf(result);
    const nextRows = Array.isArray(payload.staff) ? (payload.staff as StaffRow[]) : [];
    setRows(nextRows);
    setKpis((payload.kpis as Record<string, unknown>) ?? {});
    setError(null);
    if (selectedId == null && nextRows[0]) {
      setSelectedId(userIdOf(nextRows[0]));
    }
  }, [search, selectedId]);

  const loadEditor = useCallback(async (userId: number) => {
    setEditLoading(true);
    setError(null);
    const result = await laravelRequest<StaffEditCatalog>(adminUserEditPath(userId), {
      method: "GET",
      headers: { Accept: "application/json" },
      retryCsrfOnce: false,
    });
    setEditLoading(false);
    if (!result.ok) {
      setError(result.message ?? "Could not load staff editor.");
      setEditCatalog(null);
      return;
    }
    const data = result.data;
    setEditCatalog(data);
    setEditName(data.user?.name ?? "");
    setEditEmail(data.user?.email ?? "");
    setEditPhone(data.user?.phone ?? "");
    setEditJobTitle(data.staffProfile?.job_title ?? "Staff");
    setEditDepartment(data.staffProfile?.department ?? "Operations");
    setEditStatus(data.user?.status ?? "active");
    setSelectedPerms(
      Array.isArray(data.selectedStaffPermissions) ? [...data.selectedStaffPermissions] : [],
    );
  }, []);

  useEffect(() => {
    if (!isLive) return;
    void refresh();
  }, [isLive]);

  useEffect(() => {
    if (!isLive || selectedId == null) return;
    void loadEditor(selectedId);
  }, [isLive, selectedId, loadEditor]);

  async function onCreate() {
    if (creating) return;
    setCreating(true);
    setError(null);
    setSuccess(null);
    const result = await laravelRequest(adminUserStorePath(), {
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      json: {
        name: createForm.name,
        email: createForm.email,
        phone: createForm.phone || null,
        account_type: "staff",
        status: createForm.status,
        role_title: createForm.role_title,
        department: createForm.department,
        staff_permissions_configured: true,
        staff_permissions: selectedPerms.length ? selectedPerms : [],
        send_invite: createForm.send_invite,
      },
      retryCsrfOnce: true,
    });
    setCreating(false);
    if (!result.ok) {
      setError(result.message ?? "Could not create staff user.");
      return;
    }
    const payload = payloadOf(result) as { user?: { id?: string }; message?: string };
    const createdId = Number(payload.user?.id ?? 0);
    setSuccess(payload.message ?? "Staff user created.");
    setShowCreate(false);
    setCreateForm(emptyCreateForm());
    await refresh();
    if (createdId > 0) setSelectedId(createdId);
  }

  async function onSave() {
    if (!selectedId || saving || !editCatalog?.user) return;
    setSaving(true);
    setError(null);
    setSuccess(null);
    const result = await laravelRequest(adminUserUpdatePath(selectedId), {
      method: "PATCH",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      json: {
        name: editName,
        email: editEmail,
        phone: editPhone || null,
        account_type: "staff",
        status: editStatus,
        role_title: editJobTitle,
        department: editDepartment,
        staff_permissions_configured: true,
        staff_permissions: selectedPerms,
      },
      retryCsrfOnce: true,
    });
    setSaving(false);
    if (!result.ok) {
      setError(result.message ?? "Could not save staff user.");
      return;
    }
    setSuccess("Staff user updated.");
    await refresh();
    await loadEditor(selectedId);
  }

  async function onToggleStatus() {
    if (!selectedId || statusBusy) return;
    const active = editStatus === "active";
    setStatusBusy(true);
    setError(null);
    setSuccess(null);
    const path = active ? userSuspendPath(String(selectedId)) : userActivatePath(String(selectedId));
    const result = await laravelRequest(path, {
      method: "PATCH",
      headers: { Accept: "application/json" },
      retryCsrfOnce: true,
    });
    setStatusBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Could not update staff status.");
      return;
    }
    setSuccess(active ? "Staff suspended." : "Staff activated.");
    await refresh();
    await loadEditor(selectedId);
  }

  async function openCreate() {
    setShowCreate(true);
    setSuccess(null);
    setError(null);
    const result = await laravelRequest<StaffEditCatalog>(adminUserCreatePath(), {
      method: "GET",
      headers: { Accept: "application/json" },
      retryCsrfOnce: false,
    });
    if (result.ok && result.data.groupedStaffPermissions) {
      setEditCatalog((prev) => ({
        ...(prev ?? {}),
        groupedStaffPermissions: result.data.groupedStaffPermissions,
        staffPresetLabels: result.data.staffPresetLabels,
        staffPresetPermissions: result.data.staffPresetPermissions,
      }));
    }
  }

  function applyPreset(key: string) {
    const perms = editCatalog?.staffPresetPermissions?.[key];
    if (Array.isArray(perms)) setSelectedPerms([...perms]);
  }

  function togglePerm(key: string) {
    setSelectedPerms((prev) => (prev.includes(key) ? prev.filter((k) => k !== key) : [...prev, key]));
  }

  if (!isLive) {
    return (
      <PageContainer>
        <PageHeader title="Staff" description="Available in live dashboard mode only." />
      </PageContainer>
    );
  }

  const groups = editCatalog?.groupedStaffPermissions ?? {};
  const effective = editCatalog?.groupedEffectiveAccess ?? {};
  const presets = editCatalog?.staffPresetLabels ?? {};

  return (
    <PageContainer>
      <PageHeader
        title="Staff Management"
        description="Create, edit, activate/deactivate, and assign staff portal permissions on the current User + StaffProfile domain."
        actions={
          <Button type="button" size="sm" onClick={() => void openCreate()} data-testid="staff-create-open">
            Create staff
          </Button>
        }
      />
      <div className="grid gap-3 sm:grid-cols-4">
        {["total", "active", "inactive", "assigned_bookings"].map((key) => (
          <Card key={key}>
            <div className="text-xs text-jp-muted">{key}</div>
            <CardTitle className="mt-1 text-2xl">{String(kpis[key] ?? 0)}</CardTitle>
          </Card>
        ))}
      </div>
      <div className="flex flex-wrap gap-2">
        <Input
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          placeholder="Search staff"
          className="max-w-xs"
          data-testid="staff-search"
        />
        <Button type="button" size="sm" onClick={() => void refresh()}>
          Search
        </Button>
      </div>
      {error ? (
        <p className="text-sm text-red-700" role="alert" data-testid="staff-error">
          {error}
        </p>
      ) : null}
      {success ? (
        <p className="text-sm text-emerald-700" data-testid="staff-success">
          {success}
        </p>
      ) : null}
      {showCreate ? (
        <Card data-testid="staff-create-panel">
          <CardTitle>Create staff user</CardTitle>
          <div className="mt-3 grid gap-3 sm:grid-cols-2">
            <label className="text-sm">
              <span className="text-xs text-jp-muted">Name</span>
              <Input
                value={createForm.name}
                onChange={(e) => setCreateForm((f) => ({ ...f, name: e.target.value }))}
                data-testid="staff-create-name"
              />
            </label>
            <label className="text-sm">
              <span className="text-xs text-jp-muted">Email</span>
              <Input
                type="email"
                value={createForm.email}
                onChange={(e) => setCreateForm((f) => ({ ...f, email: e.target.value }))}
                data-testid="staff-create-email"
              />
            </label>
            <label className="text-sm">
              <span className="text-xs text-jp-muted">Phone</span>
              <Input
                value={createForm.phone}
                onChange={(e) => setCreateForm((f) => ({ ...f, phone: e.target.value }))}
              />
            </label>
            <label className="text-sm">
              <span className="text-xs text-jp-muted">Job title</span>
              <Input
                value={createForm.role_title}
                onChange={(e) => setCreateForm((f) => ({ ...f, role_title: e.target.value }))}
                data-testid="staff-create-job-title"
              />
            </label>
            <label className="text-sm">
              <span className="text-xs text-jp-muted">Department</span>
              <Input
                value={createForm.department}
                onChange={(e) => setCreateForm((f) => ({ ...f, department: e.target.value }))}
                data-testid="staff-create-department"
              />
            </label>
            <label className="text-sm">
              <span className="text-xs text-jp-muted">Status</span>
              <select
                className="mt-1 w-full rounded-lg border border-jp-border px-3 py-2 text-sm"
                value={createForm.status}
                onChange={(e) => setCreateForm((f) => ({ ...f, status: e.target.value }))}
              >
                <option value="active">active</option>
                <option value="invited">invited</option>
                <option value="inactive">inactive</option>
                <option value="suspended">suspended</option>
              </select>
            </label>
          </div>
          <label className="mt-3 flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={createForm.send_invite}
              onChange={(e) => setCreateForm((f) => ({ ...f, send_invite: e.target.checked }))}
            />
            Send invite email
          </label>
          <div className="mt-4 flex gap-2">
            <Button
              type="button"
              size="sm"
              onClick={() => void onCreate()}
              disabled={creating || !createForm.name || !createForm.email}
              data-testid="staff-create-submit"
            >
              {creating ? "Creating…" : "Create staff"}
            </Button>
            <Button type="button" size="sm" variant="secondary" onClick={() => setShowCreate(false)}>
              Cancel
            </Button>
          </div>
        </Card>
      ) : null}
      {rows.length === 0 && !error ? (
        <p className="text-sm text-jp-muted">{emptyListDescription(isLive, "Staff records")}</p>
      ) : null}
      <Card className="overflow-x-auto" data-testid="staff-directory">
        <table className="min-w-full text-left text-sm">
          <thead>
            <tr className="border-b text-jp-muted">
              <th className="px-2 py-2">Code</th>
              <th className="px-2 py-2">Name</th>
              <th className="px-2 py-2">Role</th>
              <th className="px-2 py-2">Status</th>
              <th className="px-2 py-2">Bookings</th>
              <th className="px-2 py-2" />
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => {
              const uid = userIdOf(row);
              return (
                <tr key={uid} className="border-b last:border-0">
                  <td className="px-2 py-2 font-mono text-xs">{row.staff_code}</td>
                  <td className="px-2 py-2">
                    {row.name}
                    <div className="text-xs text-jp-muted">{row.email}</div>
                  </td>
                  <td className="px-2 py-2">
                    {row.job_title}
                    <div className="text-xs text-jp-muted">{row.department}</div>
                  </td>
                  <td className="px-2 py-2">{row.status}</td>
                  <td className="px-2 py-2">{row.assigned_bookings ?? 0}</td>
                  <td className="px-2 py-2 text-right">
                    <Button
                      type="button"
                      size="sm"
                      variant={selectedId === uid ? "primary" : "secondary"}
                      onClick={() => setSelectedId(uid)}
                      data-testid={`staff-select-${uid}`}
                    >
                      Manage
                    </Button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </Card>
      {selected ? (
        <Card data-testid="staff-editor">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <CardTitle>{selected.name}</CardTitle>
              <p className="mt-1 text-xs text-jp-muted">
                {selected.staff_code} · user #{userIdOf(selected)}
              </p>
            </div>
            <Button
              type="button"
              size="sm"
              variant="secondary"
              onClick={() => void onToggleStatus()}
              disabled={statusBusy || editLoading}
              data-testid="staff-toggle-status"
            >
              {statusBusy
                ? "Updating…"
                : editStatus === "active"
                  ? "Suspend"
                  : "Activate"}
            </Button>
          </div>
          {editLoading ? (
            <p className="mt-3 text-sm text-jp-muted">Loading staff editor…</p>
          ) : (
            <>
              <div className="mt-3 grid gap-3 sm:grid-cols-2">
                <label className="text-sm">
                  <span className="text-xs text-jp-muted">Name</span>
                  <Input
                    value={editName}
                    onChange={(e) => setEditName(e.target.value)}
                    data-testid="staff-edit-name"
                  />
                </label>
                <label className="text-sm">
                  <span className="text-xs text-jp-muted">Email</span>
                  <Input
                    type="email"
                    value={editEmail}
                    onChange={(e) => setEditEmail(e.target.value)}
                    data-testid="staff-edit-email"
                  />
                </label>
                <label className="text-sm">
                  <span className="text-xs text-jp-muted">Phone</span>
                  <Input value={editPhone} onChange={(e) => setEditPhone(e.target.value)} />
                </label>
                <label className="text-sm">
                  <span className="text-xs text-jp-muted">Job title</span>
                  <Input
                    value={editJobTitle}
                    onChange={(e) => setEditJobTitle(e.target.value)}
                    data-testid="staff-edit-job-title"
                  />
                </label>
                <label className="text-sm">
                  <span className="text-xs text-jp-muted">Department</span>
                  <Input
                    value={editDepartment}
                    onChange={(e) => setEditDepartment(e.target.value)}
                    data-testid="staff-edit-department"
                  />
                </label>
                <label className="text-sm">
                  <span className="text-xs text-jp-muted">Status</span>
                  <select
                    className="mt-1 w-full rounded-lg border border-jp-border px-3 py-2 text-sm"
                    value={editStatus}
                    onChange={(e) => setEditStatus(e.target.value)}
                    data-testid="staff-edit-status"
                  >
                    <option value="active">active</option>
                    <option value="invited">invited</option>
                    <option value="inactive">inactive</option>
                    <option value="suspended">suspended</option>
                  </select>
                </label>
              </div>

              {Object.keys(presets).length > 0 ? (
                <div className="mt-4">
                  <p className="text-xs font-medium text-jp-muted">Role presets</p>
                  <div className="mt-2 flex flex-wrap gap-2">
                    {Object.entries(presets).map(([key, label]) => (
                      <Button
                        key={key}
                        type="button"
                        size="sm"
                        variant="secondary"
                        onClick={() => applyPreset(key)}
                        data-testid={`staff-preset-${key}`}
                      >
                        {label}
                      </Button>
                    ))}
                  </div>
                </div>
              ) : null}

              {editCatalog?.isStaffPermissions ? (
                <section className="mt-4" data-testid="staff-permissions-panel">
                  <h3 className="text-sm font-semibold text-gray-900">Staff permissions</h3>
                  <div className="mt-2 space-y-3">
                    {Object.entries(groups).map(([groupName, items]) => (
                      <div key={groupName}>
                        <p className="text-xs font-medium text-jp-muted">{groupName}</p>
                        <div className="mt-1 grid gap-1 sm:grid-cols-2">
                          {Object.entries(items).map(([key, label]) => (
                            <label key={key} className="flex items-center gap-2 text-sm">
                              <input
                                type="checkbox"
                                checked={selectedPerms.includes(key)}
                                onChange={() => togglePerm(key)}
                                data-testid={`staff-perm-${key}`}
                              />
                              <span>{label}</span>
                            </label>
                          ))}
                        </div>
                      </div>
                    ))}
                  </div>
                </section>
              ) : null}

              {Object.keys(effective).length > 0 ? (
                <section className="mt-4" data-testid="staff-effective-access">
                  <h3 className="text-sm font-semibold text-gray-900">Effective access (role matrix)</h3>
                  <div className="mt-2 space-y-2 text-sm">
                    {Object.entries(effective).map(([groupName, items]) => (
                      <div key={groupName}>
                        <p className="text-xs font-medium text-jp-muted">{groupName}</p>
                        <ul className="mt-1 list-disc pl-5 text-xs text-gray-700">
                          {items.map((item) => (
                            <li key={item.area}>
                              {item.area}: {item.access}
                            </li>
                          ))}
                        </ul>
                      </div>
                    ))}
                  </div>
                </section>
              ) : null}

              <div className="mt-4">
                <Button
                  type="button"
                  size="sm"
                  onClick={() => void onSave()}
                  disabled={saving}
                  data-testid="staff-save"
                >
                  {saving ? "Saving…" : "Save staff"}
                </Button>
              </div>
            </>
          )}
        </Card>
      ) : null}
    </PageContainer>
  );
}
