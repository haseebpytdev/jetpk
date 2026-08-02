"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import {
  agentApiErrorMessage,
  deactivateAgentStaff,
  fetchAgentStaffDetail,
  updateAgentStaff,
} from "../services/agent-dashboard-api";
import {
  AgentDashboardErrorState,
  AgentDashboardShell,
  PermissionDeniedState,
} from "../shell/AgentDashboardShell";
import type { AgentStaffDetail } from "../types";
import type { PublicSession } from "@/types/session";

const fieldClass =
  "mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2 focus-visible:outline-none focus-visible:shadow-jp-focus";

export function AgentStaffDetailPage({ session, staffId }: { session: PublicSession; staffId: number }) {
  const router = useRouter();
  const [detail, setDetail] = useState<AgentStaffDetail | null>(null);
  const [selected, setSelected] = useState<string[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  const load = async () => {
    setLoading(true);
    setError(null);
    const result = await fetchAgentStaffDetail(staffId);
    if (!result.ok) {
      setError(result.status === 403 ? "You do not have permission to manage this staff member." : agentApiErrorMessage(result));
      setDetail(null);
    } else {
      setDetail(result.data);
      setSelected(result.data.selected_permissions ?? result.data.staff.permissions);
    }
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, [staffId]);

  const togglePermission = (key: string) => {
    setSelected((current) => (current.includes(key) ? current.filter((item) => item !== key) : [...current, key]));
  };

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!detail || submitting || !detail.capabilities.can_update) return;
    setSubmitting(true);
    setError(null);
    const form = new FormData(event.currentTarget);
    const result = await updateAgentStaff(staffId, {
      name: String(form.get("name") ?? ""),
      email: String(form.get("email") ?? ""),
      phone: String(form.get("phone") ?? "") || undefined,
      status: String(form.get("status") ?? "active"),
      password: String(form.get("password") ?? "") || undefined,
      permissions: detail.capabilities.can_update_permissions ? selected : undefined,
    });
    if (!result.ok) {
      setError(agentApiErrorMessage(result));
      setSubmitting(false);
      return;
    }
    await load();
    setSubmitting(false);
  };

  const handleDeactivate = async () => {
    if (!detail?.capabilities.can_deactivate || !window.confirm("Deactivate this staff member?")) return;
    const result = await deactivateAgentStaff(staffId);
    if (!result.ok) setError(agentApiErrorMessage(result));
    else router.push("/agent/staff");
  };

  return (
    <AgentDashboardShell session={session} title="Manage staff">
      <Link href="/agent/staff" className="mb-4 inline-block text-jp-sm text-jp-primary">
        Back to staff
      </Link>
      {loading ? <p className="text-jp-sm text-jp-muted">Loading staff member…</p> : null}
      {error === "You do not have permission to manage this staff member." ? (
        <PermissionDeniedState message={error} />
      ) : null}
      {error && error !== "You do not have permission to manage this staff member." ? (
        <AgentDashboardErrorState message={error} onRetry={load} />
      ) : null}
      {detail ? (
        <form className="max-w-xl space-y-4" onSubmit={handleSubmit} data-testid="agent-staff-edit-form">
          <label className="block text-jp-sm">
            Name
            <input name="name" defaultValue={detail.staff.name} required className={fieldClass} disabled={!detail.capabilities.can_update} />
          </label>
          <label className="block text-jp-sm">
            Email
            <input name="email" type="email" defaultValue={detail.staff.email} required className={fieldClass} disabled={!detail.capabilities.can_update} />
          </label>
          <label className="block text-jp-sm">
            Phone
            <input name="phone" defaultValue={detail.staff.phone ?? ""} className={fieldClass} disabled={!detail.capabilities.can_update} />
          </label>
          <label className="block text-jp-sm">
            Status
            <select name="status" defaultValue={detail.staff.status} className={fieldClass} disabled={!detail.capabilities.can_update}>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="suspended">Suspended</option>
            </select>
          </label>
          {detail.capabilities.can_update ? (
            <label className="block text-jp-sm">
              New password (optional)
              <input name="password" type="password" minLength={8} className={fieldClass} />
            </label>
          ) : null}
          {detail.capabilities.can_update_permissions ? (
            <fieldset>
              <legend className="text-jp-sm font-semibold">Permissions</legend>
              <div className="mt-2 space-y-2">
                {Object.entries(detail.permission_labels).map(([key, label]) => (
                  <label key={key} className="flex items-center gap-2 text-jp-sm">
                    <input type="checkbox" checked={selected.includes(key)} onChange={() => togglePermission(key)} />
                    {label}
                  </label>
                ))}
              </div>
            </fieldset>
          ) : null}
          {detail.capabilities.can_update ? (
            <PrimaryButton type="submit" disabled={submitting}>
              {submitting ? "Saving…" : "Save changes"}
            </PrimaryButton>
          ) : null}
          {detail.capabilities.can_deactivate ? (
            <button type="button" className="ml-3 text-jp-sm text-red-700" onClick={() => void handleDeactivate()}>
              Deactivate staff
            </button>
          ) : null}
        </form>
      ) : null}
    </AgentDashboardShell>
  );
}
