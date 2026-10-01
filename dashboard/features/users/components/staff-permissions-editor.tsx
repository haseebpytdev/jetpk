"use client";

import { useEffect, useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import { adminUserEditPath, adminUserUpdatePath } from "@/lib/api/portal-paths";
import { laravelUserIdFromPublicId } from "@/lib/users/public-user-id";

type StaffEditPayload = {
  ok?: boolean;
  isStaffPermissions?: boolean;
  user?: {
    id?: string;
    name?: string;
    email?: string;
    account_type?: string;
    status?: string;
  };
  selectedStaffPermissions?: string[];
  groupedStaffPermissions?: Record<string, Record<string, string>>;
  staffAccessModeLabel?: string | null;
  staffAccessModeHelp?: string | null;
  usesLegacyStaffPermissions?: boolean;
};

type Props = {
  userId: string;
};

export function StaffPermissionsEditor({ userId }: Props) {
  const laravelId = useMemo(() => laravelUserIdFromPublicId(userId), [userId]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [payload, setPayload] = useState<StaffEditPayload | null>(null);
  const [selected, setSelected] = useState<string[]>([]);

  useEffect(() => {
    let cancelled = false;
    async function load() {
      setLoading(true);
      setError(null);
      setSuccess(null);
      const result = await laravelRequest<StaffEditPayload>(adminUserEditPath(laravelId), {
        method: "GET",
        headers: { Accept: "application/json" },
        retryCsrfOnce: false,
      });
      if (cancelled) return;
      setLoading(false);
      if (!result.ok) {
        setError(result.message ?? "Could not load staff permissions.");
        setPayload(null);
        return;
      }
      const data = result.data;
      if (!data.isStaffPermissions) {
        setPayload(data);
        return;
      }
      setPayload(data);
      setSelected(Array.isArray(data.selectedStaffPermissions) ? [...data.selectedStaffPermissions] : []);
    }
    void load();
    return () => {
      cancelled = true;
    };
  }, [laravelId]);

  if (loading) {
    return (
      <p className="text-sm text-jp-muted" data-testid="staff-permissions-loading">
        Loading staff permissions…
      </p>
    );
  }

  if (error && !payload) {
    return (
      <p className="text-sm text-red-700" data-testid="staff-permissions-error" role="alert">
        {error}
      </p>
    );
  }

  if (!payload?.isStaffPermissions) {
    return null;
  }

  const groups = payload.groupedStaffPermissions ?? {};

  async function onSave() {
    if (!payload?.user || saving) return;
    setSaving(true);
    setError(null);
    setSuccess(null);

    const result = await laravelRequest<StaffEditPayload & { message?: string; selectedStaffPermissions?: string[] }>(
      adminUserUpdatePath(laravelId),
      {
        method: "PATCH",
        headers: { Accept: "application/json", "Content-Type": "application/json" },
        json: {
          name: payload.user.name,
          email: payload.user.email,
          account_type: payload.user.account_type,
          status: payload.user.status,
          staff_permissions_configured: true,
          staff_permissions: selected,
        },
        retryCsrfOnce: true,
      },
    );

    setSaving(false);
    if (!result.ok) {
      setError(result.message ?? "Could not save staff permissions.");
      return;
    }

    const next = Array.isArray(result.data.selectedStaffPermissions)
      ? result.data.selectedStaffPermissions
      : selected;
    setSelected([...next]);
    setPayload((prev) =>
      prev
        ? {
            ...prev,
            selectedStaffPermissions: next,
            usesLegacyStaffPermissions: false,
            staffAccessModeLabel: "Permission-based access",
            staffAccessModeHelp: null,
          }
        : prev,
    );
    setSuccess(result.data.message ?? "Staff permissions updated.");
  }

  function toggle(key: string) {
    setSelected((prev) => (prev.includes(key) ? prev.filter((k) => k !== key) : [...prev, key]));
  }

  return (
    <section aria-labelledby="staff-permissions-heading" data-testid="staff-permissions-editor">
      <h3 id="staff-permissions-heading" className="text-sm font-semibold text-gray-900">
        Staff permissions
      </h3>
      {payload.staffAccessModeLabel ? (
        <p className="mt-1 text-xs text-jp-muted">{payload.staffAccessModeLabel}</p>
      ) : null}
      {payload.staffAccessModeHelp ? (
        <p className="mt-1 text-xs text-amber-800">{payload.staffAccessModeHelp}</p>
      ) : null}

      <div className="mt-3 space-y-4">
        {Object.entries(groups).map(([groupName, items]) => (
          <fieldset key={groupName} className="space-y-2">
            <legend className="text-xs font-semibold uppercase tracking-wide text-jp-muted">{groupName}</legend>
            <div className="space-y-1.5">
              {Object.entries(items).map(([key, label]) => (
                <label key={key} className="flex items-start gap-2 text-sm">
                  <input
                    type="checkbox"
                    className="mt-1"
                    checked={selected.includes(key)}
                    onChange={() => toggle(key)}
                    data-testid={`staff-perm-${key}`}
                  />
                  <span>
                    <span className="font-medium text-gray-900">{label}</span>
                    <span className="ml-2 font-mono text-[11px] text-jp-muted">{key}</span>
                  </span>
                </label>
              ))}
            </div>
          </fieldset>
        ))}
      </div>

      {error ? (
        <p className="mt-3 text-sm text-red-700" role="alert">
          {error}
        </p>
      ) : null}
      {success ? (
        <p className="mt-3 text-sm text-emerald-700" data-testid="staff-permissions-success">
          {success}
        </p>
      ) : null}

      <div className="mt-4">
        <Button type="button" size="sm" onClick={() => void onSave()} disabled={saving}>
          {saving ? "Saving…" : "Save staff permissions"}
        </Button>
      </div>
    </section>
  );
}
