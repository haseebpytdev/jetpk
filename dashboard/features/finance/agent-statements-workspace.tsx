"use client";

import { useEffect, useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { financeStatementExportPath } from "@/lib/api/portal-paths";
import { loadFinanceStatement, loadFinanceStatements } from "@/services/operational-api";

type StatementRow = {
  agency_id: string;
  agency_name: string;
  wallet_balance: number;
  ledger_liability: number;
  difference: number;
  reconciliation_status: string;
};

export function AgentStatementsWorkspace() {
  const isLive = useDashboardLiveMode();
  const [rows, setRows] = useState<StatementRow[]>([]);
  const [selectedId, setSelectedId] = useState("");
  const [detail, setDetail] = useState<Record<string, unknown> | null>(null);
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!isLive) return;
    void loadFinanceStatements().then((result) => {
      if (!result.ok) {
        setError(result.message ?? "Unable to load statements.");
        return;
      }
      const payload = ("data" in result ? result.data : result) as { rows?: StatementRow[] };
      const nextRows = payload.rows ?? [];
      setRows(nextRows);
      if (nextRows[0]) setSelectedId(nextRows[0].agency_id);
    });
  }, [isLive]);

  async function loadDetail(agencyId: string) {
    if (!agencyId) return;
    setBusy(true);
    setError(null);
    const result = await loadFinanceStatement(agencyId, {
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
    });
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Unable to load statement detail.");
      setDetail(null);
      return;
    }
    setDetail(("data" in result ? result.data : result) as Record<string, unknown>);
  }

  return (
    <section className="space-y-3 rounded-xl border border-jp-border bg-white p-4" data-testid="agent-statements-workspace">
      <div>
        <h3 className="text-sm font-semibold text-gray-900">Agent finance statements</h3>
        <p className="mt-1 text-xs text-jp-muted">Read/export via FinanceStatementController (wallet vs ledger).</p>
      </div>
      {!isLive ? <p className="text-xs text-jp-muted">Available in live dashboard mode only.</p> : null}
      {error ? <p className="text-sm text-red-600">{error}</p> : null}

      <div className="grid gap-3 sm:grid-cols-3">
        <label className="block text-xs font-medium text-jp-muted">
          Agency
          <select
            className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
            value={selectedId}
            onChange={(e) => setSelectedId(e.target.value)}
            data-testid="agent-statement-agency"
          >
            {rows.map((row) => (
              <option key={row.agency_id} value={row.agency_id}>
                {row.agency_name} ({row.reconciliation_status})
              </option>
            ))}
          </select>
        </label>
        <label className="block text-xs font-medium text-jp-muted">
          From
          <input type="date" className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} />
        </label>
        <label className="block text-xs font-medium text-jp-muted">
          To
          <input type="date" className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" value={dateTo} onChange={(e) => setDateTo(e.target.value)} />
        </label>
      </div>

      <div className="flex flex-wrap gap-2">
        <button
          type="button"
          className="min-h-11 rounded-xl bg-jp-accent px-4 text-sm text-white disabled:opacity-60"
          disabled={!isLive || !selectedId || busy}
          data-testid="agent-statement-load"
          onClick={() => void loadDetail(selectedId)}
        >
          {busy ? "Loading…" : "Load statement"}
        </button>
        {selectedId ? (
          <a
            className="inline-flex min-h-11 items-center rounded-xl border border-jp-border px-4 text-sm"
            href={financeStatementExportPath(selectedId, {
              date_from: dateFrom || undefined,
              date_to: dateTo || undefined,
            })}
            data-testid="agent-statement-export"
          >
            Export CSV
          </a>
        ) : null}
      </div>

      {detail ? (
        <div className="space-y-2 text-sm" data-testid="agent-statement-detail">
          <p>
            Period {(detail.period as { from?: string; to?: string } | undefined)?.from} →{" "}
            {(detail.period as { from?: string; to?: string } | undefined)?.to}
          </p>
          <p>
            Opening {String(detail.opening_balance)} · Closing {String(detail.closing_balance)} · Debits{" "}
            {String(detail.total_debits)} · Credits {String(detail.total_credits)}
          </p>
          <ul className="max-h-64 space-y-1 overflow-auto rounded-lg border border-jp-border p-2">
            {((detail.movements as Array<Record<string, unknown>> | undefined) ?? []).map((movement, index) => (
              <li key={`${String(movement.reference)}-${index}`} className="flex justify-between gap-2 text-xs">
                <span>
                  {String(movement.date)} · {String(movement.description)}
                </span>
                <span>
                  D {String(movement.debit)} / C {String(movement.credit)}
                </span>
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      <ul className="space-y-1 text-xs text-jp-muted" data-testid="agent-statements-index">
        {rows.map((row) => (
          <li key={row.agency_id}>
            {row.agency_name}: wallet {row.wallet_balance} / ledger {row.ledger_liability} (diff {row.difference})
          </li>
        ))}
      </ul>
    </section>
  );
}
