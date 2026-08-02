"use client";

import { useEffect, useState } from "react";
import { agentApiErrorMessage, fetchAgentReports } from "../services/agent-dashboard-api";
import {
  AgentDashboardErrorState,
  AgentDashboardShell,
  AgentEmptyState,
  PermissionDeniedState,
} from "../shell/AgentDashboardShell";
import type { AgentReportsOverview } from "../types";
import type { PublicSession } from "@/types/session";

export function AgentReportsPage({ session }: { session: PublicSession }) {
  const [report, setReport] = useState<AgentReportsOverview | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    setError(null);
    const result = await fetchAgentReports();
    if (!result.ok) {
      setError(result.status === 403 ? "You do not have permission to view reports." : agentApiErrorMessage(result));
      setReport(null);
    } else {
      setReport(result.data);
    }
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  return (
    <AgentDashboardShell session={session} title="Agency reports">
      {loading ? <p className="text-jp-sm text-jp-muted">Loading reports…</p> : null}
      {error === "You do not have permission to view reports." ? <PermissionDeniedState message={error} /> : null}
      {error && error !== "You do not have permission to view reports." ? (
        <AgentDashboardErrorState message={error} onRetry={load} />
      ) : null}
      {report && !report.has_live_data ? (
        <AgentEmptyState title="No report data yet" description="Agency booking activity will appear here once bookings exist in the selected period." />
      ) : null}
      {report?.has_live_data ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-testid="agent-reports-summary">
          {Object.entries(report.summary).map(([key, value]) => (
            <div key={key} className="rounded-jp-lg border border-jp-border p-4">
              <p className="text-jp-xs uppercase tracking-wide text-jp-muted">{key.replaceAll("_", " ")}</p>
              <p className="mt-1 text-jp-xl font-semibold text-jp-text">{value}</p>
            </div>
          ))}
        </div>
      ) : null}
    </AgentDashboardShell>
  );
}
