"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { agentApiErrorMessage, fetchAgentCommissions } from "../services/agent-dashboard-api";
import {
  AgentDashboardErrorState,
  AgentDashboardShell,
  PermissionDeniedState,
} from "../shell/AgentDashboardShell";
import type { AgentCommissionOverview } from "../types";
import type { PublicSession } from "@/types/session";

export function AgentCommissionsPage({ session }: { session: PublicSession }) {
  const [data, setData] = useState<AgentCommissionOverview | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    void (async () => {
      const result = await fetchAgentCommissions();
      if (!result.ok) {
        setError(result.status === 403 ? "Commissions are available to agency owners only." : agentApiErrorMessage(result));
      } else {
        setData(result.data);
      }
      setLoading(false);
    })();
  }, []);

  return (
    <AgentDashboardShell session={session} title="Commissions">
      {loading ? <p className="text-jp-sm text-jp-muted">Loading commissions…</p> : null}
      {error === "Commissions are available to agency owners only." ? <PermissionDeniedState message={error} /> : null}
      {error && error !== "Commissions are available to agency owners only." ? (
        <AgentDashboardErrorState message={error} />
      ) : null}
      {data ? (
        <div data-testid="agent-commissions-overview" className="space-y-6">
          <div className="rounded-jp-lg border border-jp-border p-4">
            <p className="text-jp-sm text-jp-muted">Current balance</p>
            <p className="text-jp-2xl font-semibold text-jp-text">
              {data.totals.currency} {data.balance.toLocaleString()}
            </p>
            <p className="mt-2 text-jp-xs text-jp-muted">
              Pending {data.totals.pending.toLocaleString()} · Approved {data.totals.approved.toLocaleString()} · Paid{" "}
              {data.totals.paid.toLocaleString()}
            </p>
          </div>
          <section>
            <h2 className="text-jp-base font-semibold">Recent entries</h2>
            <ul className="mt-3 divide-y divide-jp-border rounded-jp-lg border border-jp-border">
              {data.entries.map((entry) => (
                <li key={entry.id} className="flex justify-between gap-3 p-3 text-jp-sm">
                  <span>{entry.booking_reference ?? "Booking"}</span>
                  <span>
                    {entry.currency} {entry.amount.toLocaleString()} · {entry.status}
                  </span>
                </li>
              ))}
            </ul>
          </section>
          {data.statements.length > 0 ? (
            <section>
              <h2 className="text-jp-base font-semibold">Statements</h2>
              <ul className="mt-3 space-y-2">
                {data.statements.map((statement) => (
                  <li key={statement.id}>
                    <Link href={statement.detail_url} className="text-jp-sm text-jp-primary">
                      {statement.reference} · {statement.total_amount.toLocaleString()}
                    </Link>
                  </li>
                ))}
              </ul>
            </section>
          ) : null}
        </div>
      ) : null}
    </AgentDashboardShell>
  );
}
