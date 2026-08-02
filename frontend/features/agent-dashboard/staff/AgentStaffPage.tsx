"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { agentApiErrorMessage, fetchAgentStaffList } from "../services/agent-dashboard-api";
import {
  AgentDashboardErrorState,
  AgentDashboardShell,
  AgentEmptyState,
  PermissionDeniedState,
} from "../shell/AgentDashboardShell";
import type { AgentStaffMember } from "../types";
import type { PublicSession } from "@/types/session";

export function AgentStaffPage({ session }: { session: PublicSession }) {
  const [staff, setStaff] = useState<AgentStaffMember[]>([]);
  const [canCreate, setCanCreate] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    setError(null);
    const result = await fetchAgentStaffList();
    if (!result.ok) {
      setError(result.status === 403 ? "You do not have permission to manage staff." : agentApiErrorMessage(result));
      setStaff([]);
    } else {
      setStaff(result.data.staff);
      setCanCreate(result.data.capabilities.can_create);
    }
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  return (
    <AgentDashboardShell session={session} title="Staff">
      <div className="mb-4 flex items-center justify-between gap-3">
        <p className="text-jp-sm text-jp-muted">Manage agency staff access and permissions.</p>
        {canCreate ? (
          <Link href="/agent/staff/new" className="rounded-jp-button border border-jp-border px-4 py-2 text-jp-sm font-semibold">
            Add staff
          </Link>
        ) : null}
      </div>

      {loading ? <p className="text-jp-sm text-jp-muted">Loading staff…</p> : null}
      {error === "You do not have permission to manage staff." ? <PermissionDeniedState message={error} /> : null}
      {error && error !== "You do not have permission to manage staff." ? (
        <AgentDashboardErrorState message={error} onRetry={load} />
      ) : null}
      {!loading && !error && staff.length === 0 ? (
        <AgentEmptyState title="No staff yet" description="Add staff members to delegate bookings, wallet, and support access." />
      ) : null}

      {!loading && !error && staff.length > 0 ? (
        <ul className="divide-y divide-jp-border rounded-jp-lg border border-jp-border" data-testid="agent-staff-list">
          {staff.map((member) => (
            <li key={member.id} className="flex flex-wrap items-center justify-between gap-3 p-4">
              <div>
                <p className="font-semibold text-jp-text">{member.name}</p>
                <p className="text-jp-sm text-jp-muted">{member.email}</p>
                <p className="text-jp-xs text-jp-muted">
                  {member.role_label} · {member.status} · {member.permissions_count} permissions
                </p>
              </div>
              <Link href={`/agent/staff/${member.id}`} className="text-jp-sm text-jp-primary">
                Manage
              </Link>
            </li>
          ))}
        </ul>
      ) : null}
    </AgentDashboardShell>
  );
}
