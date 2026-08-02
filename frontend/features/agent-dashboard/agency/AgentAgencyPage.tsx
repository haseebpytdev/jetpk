"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { agentApiErrorMessage, fetchAgentAgency } from "../services/agent-dashboard-api";
import {
  AgentDashboardErrorState,
  AgentDashboardShell,
  PermissionDeniedState,
} from "../shell/AgentDashboardShell";
import type { AgentAgencyProfile } from "../types";
import type { PublicSession } from "@/types/session";

export function AgentAgencyPage({ session }: { session: PublicSession }) {
  const [profile, setProfile] = useState<AgentAgencyProfile | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    void (async () => {
      const result = await fetchAgentAgency();
      if (!result.ok) {
        setError(result.status === 403 ? "You do not have permission to view agency details." : agentApiErrorMessage(result));
      } else {
        setProfile(result.data);
      }
      setLoading(false);
    })();
  }, []);

  const details = profile?.details ?? {};

  return (
    <AgentDashboardShell session={session} title="Agency profile">
      {loading ? <p className="text-jp-sm text-jp-muted">Loading agency profile…</p> : null}
      {error === "You do not have permission to view agency details." ? <PermissionDeniedState message={error} /> : null}
      {error && error !== "You do not have permission to view agency details." ? (
        <AgentDashboardErrorState message={error} />
      ) : null}
      {profile ? (
        <div className="max-w-xl space-y-3" data-testid="agent-agency-profile">
          <p className="text-jp-lg font-semibold text-jp-text">{String(details.agency_name ?? "Agency")}</p>
          <p className="text-jp-sm text-jp-muted">Code: {String(details.agent_code ?? "—")}</p>
          <p className="text-jp-sm text-jp-muted">Email: {String(details.email ?? "—")}</p>
          <p className="text-jp-sm text-jp-muted">Phone: {String(details.phone ?? "—")}</p>
          <p className="text-jp-sm text-jp-muted">
            {String(details.city ?? "—")}, {String(details.country ?? "—")}
          </p>
          {profile.wallet_summary ? (
            <p className="text-jp-sm text-jp-muted">
              Wallet: {profile.wallet_summary.currency} {profile.wallet_summary.balance.toLocaleString()}
            </p>
          ) : null}
          {profile.capabilities.can_edit_agency ? (
            <Link href="/agent/agency/edit" className="inline-block text-jp-sm text-jp-primary">
              Edit agency profile
            </Link>
          ) : null}
        </div>
      ) : null}
    </AgentDashboardShell>
  );
}
