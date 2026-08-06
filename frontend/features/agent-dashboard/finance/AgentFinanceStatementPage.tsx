"use client";

import { useEffect, useState } from "react";
import { agentApiErrorMessage, fetchAgentFinanceStatement } from "../services/agent-dashboard-api";
import {
  AgentDashboardErrorState,
  AgentDashboardShell,
  AgentEmptyState,
  PermissionDeniedState,
} from "../shell/AgentDashboardShell";
import type { AgentFinanceStatement } from "../types";
import { resolveAllowedFinanceExportUrl } from "../utils/finance-export-allowlist";
import type { PublicSession } from "@/types/session";

function formatMoney(amount: number, currency: string) {
  return `${currency} ${amount.toLocaleString()}`;
}

export function AgentFinanceStatementPage({ session }: { session: PublicSession }) {
  const [statement, setStatement] = useState<AgentFinanceStatement | null>(null);
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [denied, setDenied] = useState(false);
  const [loading, setLoading] = useState(true);

  const load = async (from = dateFrom, to = dateTo) => {
    setLoading(true);
    setError(null);
    setDenied(false);
    const result = await fetchAgentFinanceStatement({
      date_from: from || undefined,
      date_to: to || undefined,
    });
    if (!result.ok) {
      if (result.status === 403) setDenied(true);
      else setError(agentApiErrorMessage(result));
      setStatement(null);
    } else {
      setStatement(result.data);
      if (!from && result.data.period.from) setDateFrom(result.data.period.from);
      if (!to && result.data.period.to) setDateTo(result.data.period.to);
    }
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  const allowedExportUrl = resolveAllowedFinanceExportUrl(statement?.export_url);

  if (denied) {
    return (
      <AgentDashboardShell session={session} title="Agency statement">
        <PermissionDeniedState message="You do not have permission to view the agency statement." />
      </AgentDashboardShell>
    );
  }

  return (
    <AgentDashboardShell session={session} title="Agency statement">
      <form
        className="mb-4 flex flex-wrap items-end gap-3"
        data-testid="agent-finance-statement-filters"
        onSubmit={(event) => {
          event.preventDefault();
          void load(dateFrom, dateTo);
        }}
      >
        <label className="block text-jp-sm">
          From
          <input
            type="date"
            value={dateFrom}
            onChange={(event) => setDateFrom(event.target.value)}
            className="mt-1 rounded-jp-md border border-jp-border px-3 py-2"
          />
        </label>
        <label className="block text-jp-sm">
          To
          <input
            type="date"
            value={dateTo}
            onChange={(event) => setDateTo(event.target.value)}
            className="mt-1 rounded-jp-md border border-jp-border px-3 py-2"
          />
        </label>
        <button type="submit" className="rounded-jp-button border border-jp-border px-4 py-2 text-jp-sm font-semibold">
          Apply
        </button>
        {allowedExportUrl ? (
          <a href={allowedExportUrl} className="text-jp-sm text-jp-primary" data-testid="agent-finance-export">
            Export CSV
          </a>
        ) : null}
        {statement?.blade_fallback_url ? (
          <a href={statement.blade_fallback_url} className="text-jp-sm text-jp-muted">
            Blade fallback
          </a>
        ) : null}
      </form>

      {loading ? <p className="text-jp-sm text-jp-muted">Loading statement…</p> : null}
      {error ? <AgentDashboardErrorState message={error} onRetry={() => load()} /> : null}

      {statement && !loading && !error ? (
        <div data-testid="agent-finance-statement-summary" className="space-y-4">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
              <p className="text-jp-xs text-jp-muted">Opening balance</p>
              <p className="font-semibold">{formatMoney(statement.opening_balance, statement.currency)}</p>
            </div>
            <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
              <p className="text-jp-xs text-jp-muted">Closing balance</p>
              <p className="font-semibold">{formatMoney(statement.closing_balance, statement.currency)}</p>
            </div>
            <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
              <p className="text-jp-xs text-jp-muted">Total credits</p>
              <p className="font-semibold">{formatMoney(statement.total_credits, statement.currency)}</p>
            </div>
            <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
              <p className="text-jp-xs text-jp-muted">Reconciliation</p>
              <p className="font-semibold">{statement.reconciliation.status}</p>
            </div>
          </div>

          {statement.movements.length === 0 ? (
            <AgentEmptyState title="No movements in this period" description="Try a different date range." />
          ) : (
            <div className="space-y-3" data-testid="agent-finance-statement-movements">
              {statement.movements.map((movement, index) => (
                <article key={`${movement.reference}-${index}`} className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="font-semibold text-jp-text">{movement.description || movement.type}</p>
                      <p className="text-jp-sm text-jp-muted">
                        {movement.date} · {movement.reference}
                        {movement.booking_reference ? ` · ${movement.booking_reference}` : ""}
                      </p>
                    </div>
                    <div className="text-right text-jp-sm">
                      {movement.credit > 0 ? (
                        <p className="text-emerald-700">+{formatMoney(movement.credit, statement.currency)}</p>
                      ) : null}
                      {movement.debit > 0 ? (
                        <p>−{formatMoney(movement.debit, statement.currency)}</p>
                      ) : null}
                      <p className="text-jp-muted">Balance: {formatMoney(movement.running_balance, statement.currency)}</p>
                    </div>
                  </div>
                </article>
              ))}
            </div>
          )}
        </div>
      ) : null}
    </AgentDashboardShell>
  );
}
