"use client";

import { useCallback, useEffect, useMemo, useState, type FormEvent } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import {
  buildFinanceDashboardExportHref,
  buildReconciliationExportHref,
  listFinanceAdjustments,
  loadFinanceAdjustmentCreate,
  reverseFinanceAdjustment,
  storeFinanceAdjustment,
} from "@/services/operational-api";
import { formatCurrency } from "@/lib/format";
import { AgentStatementsWorkspace } from "@/features/finance/agent-statements-workspace";

type AgencyOption = { id: string; name: string };

type AdjustmentRow = {
  id: string;
  agency_id?: string;
  agency_name?: string | null;
  type: string;
  amount: number;
  currency?: string;
  adjustment_reason?: string | null;
  note?: string | null;
  can_reverse?: boolean;
  is_reversal?: boolean;
  created_at?: string | null;
  created_by?: string | null;
  reference?: string | null;
  status?: string | null;
};

const REASON_FALLBACK = [
  "bank_correction",
  "duplicate_payment_correction",
  "refund_correction",
  "commission_correction",
  "opening_balance_correction",
  "other",
];

function newIdempotencyKey(): string {
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return crypto.randomUUID();
  }
  return `adj-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function reasonLabel(reason: string): string {
  return reason.replaceAll("_", " ").replace(/^\w/, (c) => c.toUpperCase());
}

export function AccountingWorkspace() {
  const isLive = useDashboardLiveMode();
  const [rows, setRows] = useState<AdjustmentRow[]>([]);
  const [agencies, setAgencies] = useState<AgencyOption[]>([]);
  const [reasons, setReasons] = useState<string[]>(REASON_FALLBACK);
  const [agencyId, setAgencyId] = useState("");
  const [walletLabel, setWalletLabel] = useState<string | null>(null);
  const [walletId, setWalletId] = useState<string | null>(null);
  const [adjustmentType, setAdjustmentType] = useState<"manual_credit" | "manual_debit">("manual_credit");
  const [amount, setAmount] = useState("");
  const [reason, setReason] = useState("bank_correction");
  const [note, setNote] = useState("");
  const [confirmed, setConfirmed] = useState(false);
  const [idempotencyKey, setIdempotencyKey] = useState(newIdempotencyKey);
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [reversalReasons, setReversalReasons] = useState<Record<string, string>>({});

  const refreshList = useCallback(async () => {
    const result = await listFinanceAdjustments();
    if (!result.ok) {
      setError(result.message ?? "Unable to load adjustments.");
      return;
    }
    setRows((result.transactions as AdjustmentRow[] | undefined) ?? []);
    if (Array.isArray(result.reason_categories) && result.reason_categories.length > 0) {
      setReasons(result.reason_categories as string[]);
    }
  }, []);

  const loadCreateMeta = useCallback(async (selectedAgency?: string) => {
    const result = await loadFinanceAdjustmentCreate(selectedAgency || undefined);
    if (!result.ok) {
      setError(result.message ?? "Unable to load adjustment form metadata.");
      return;
    }
    setAgencies(result.agencies ?? []);
    if (Array.isArray(result.reason_categories) && result.reason_categories.length > 0) {
      setReasons(result.reason_categories);
    }
    if (typeof result.idempotency_key === "string" && result.idempotency_key) {
      setIdempotencyKey(result.idempotency_key);
    }
    const summary = result.canonical_summary as
      | { wallet_id?: number | string; owner_label?: string; balance?: number; currency?: string; has_duplicate_wallets?: boolean }
      | null
      | undefined;
    if (summary?.wallet_id) {
      setWalletId(String(summary.wallet_id));
      setWalletLabel(
        `Wallet #${summary.wallet_id} — ${summary.owner_label ?? "canonical"} (${summary.currency ?? "PKR"} ${Number(summary.balance ?? 0).toFixed(2)})`,
      );
    } else {
      setWalletId(null);
      setWalletLabel(selectedAgency ? "Canonical wallet will be resolved on submit." : null);
    }
  }, []);

  useEffect(() => {
    if (!isLive) return;
    void refreshList();
    void loadCreateMeta();
  }, [isLive, refreshList, loadCreateMeta]);

  const canSubmit = useMemo(() => {
    return Boolean(agencyId && Number(amount) > 0 && reason && confirmed && !busy);
  }, [agencyId, amount, reason, confirmed, busy]);

  async function onAgencyChange(next: string) {
    setAgencyId(next);
    setError(null);
    if (next) {
      await loadCreateMeta(next);
    } else {
      setWalletId(null);
      setWalletLabel(null);
    }
  }

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    if (!canSubmit) return;
    setBusy("store");
    setError(null);
    setMessage(null);
    const result = await storeFinanceAdjustment({
      agency_id: agencyId,
      wallet_id: walletId,
      adjustment_type: adjustmentType,
      amount: Number(amount),
      adjustment_reason: reason,
      adjustment_note: note.trim() || null,
      idempotency_key: idempotencyKey,
      confirmation: true,
    });
    setBusy(null);
    if (!result.ok) {
      setError(result.message ?? "Adjustment failed.");
      return;
    }
    setMessage(
      result.idempotent_replay
        ? "Idempotent replay — existing adjustment returned."
        : `${adjustmentType === "manual_credit" ? "Credit" : "Debit"} posted to wallet and ledger.`,
    );
    setAmount("");
    setNote("");
    setConfirmed(false);
    setIdempotencyKey(newIdempotencyKey());
    await refreshList();
    if (agencyId) await loadCreateMeta(agencyId);
  }

  async function onReverse(row: AdjustmentRow) {
    const reversalReason = (reversalReasons[row.id] ?? "").trim();
    if (reversalReason.length < 3) {
      setError("Enter a reversal reason (min 3 characters) before reversing.");
      return;
    }
    setBusy(`reverse-${row.id}`);
    setError(null);
    setMessage(null);
    const result = await reverseFinanceAdjustment(row.id, reversalReason, true);
    setBusy(null);
    if (!result.ok) {
      setError(result.message ?? "Reversal failed.");
      return;
    }
    setMessage(`Adjustment #${row.id} reversed.`);
    await refreshList();
    if (agencyId) await loadCreateMeta(agencyId);
  }

  if (!isLive) {
    return (
      <p className="text-sm text-jp-muted" data-testid="accounting-preview">
        Accounting mutations require live dashboard mode. Ledger authority remains Laravel FinanceAdjustmentController.
      </p>
    );
  }

  return (
    <div className="space-y-6" data-testid="accounting-workspace">
      <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950" data-testid="finance-adjustment-warning">
        Manual credit, debit, and reversal change agency wallet balances and create audited ledger entries. Confirm every
        mutation. Production SMTP and customer emails are out of scope here.
      </div>

      {error ? (
        <p className="text-sm text-red-600" role="alert">
          {error}
        </p>
      ) : null}
      {message ? <p className="text-sm text-green-700">{message}</p> : null}

      <form
        onSubmit={(e) => void onSubmit(e)}
        className="space-y-4 rounded-xl border border-jp-border bg-white p-4"
        data-testid="finance-adjustment-create-form"
      >
        <h2 className="text-sm font-semibold text-gray-900">Post wallet adjustment</h2>
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="block text-xs">
            Agency
            <select
              className="mt-1 w-full rounded-lg border border-jp-border px-2 py-2 text-sm"
              value={agencyId}
              onChange={(e) => void onAgencyChange(e.target.value)}
              required
              data-testid="finance-adjustment-agency"
            >
              <option value="">Select agency…</option>
              {agencies.map((agency) => (
                <option key={agency.id} value={agency.id}>
                  {agency.name}
                </option>
              ))}
            </select>
          </label>
          <div className="text-xs">
            <p className="font-medium text-gray-900">Canonical wallet</p>
            <p className="mt-1 text-jp-muted" data-testid="finance-adjustment-canonical-wallet">
              {walletLabel ?? "Select an agency to resolve the operational wallet."}
            </p>
          </div>
          <fieldset className="space-y-2 text-xs sm:col-span-2">
            <legend className="font-medium text-gray-900">Adjustment type</legend>
            <label className="mr-4 inline-flex items-center gap-2">
              <input
                type="radio"
                name="adjustment_type"
                checked={adjustmentType === "manual_credit"}
                onChange={() => setAdjustmentType("manual_credit")}
              />
              Credit (increase balance)
            </label>
            <label className="inline-flex items-center gap-2">
              <input
                type="radio"
                name="adjustment_type"
                checked={adjustmentType === "manual_debit"}
                onChange={() => setAdjustmentType("manual_debit")}
              />
              Debit (decrease balance)
            </label>
          </fieldset>
          <label className="block text-xs">
            Amount (PKR)
            <input
              type="number"
              min="0.01"
              step="0.01"
              className="mt-1 w-full rounded-lg border border-jp-border px-2 py-2 text-sm"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              required
              data-testid="finance-adjustment-amount"
            />
          </label>
          <label className="block text-xs">
            Reason
            <select
              className="mt-1 w-full rounded-lg border border-jp-border px-2 py-2 text-sm"
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              required
              data-testid="finance-adjustment-reason"
            >
              {reasons.map((item) => (
                <option key={item} value={item}>
                  {reasonLabel(item)}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-xs sm:col-span-2">
            Note (optional)
            <textarea
              className="mt-1 w-full rounded-lg border border-jp-border px-2 py-2 text-sm"
              rows={3}
              value={note}
              onChange={(e) => setNote(e.target.value)}
              data-testid="finance-adjustment-note"
            />
          </label>
          <input type="hidden" value={idempotencyKey} data-testid="finance-adjustment-idempotency-key" readOnly />
          <label className="flex items-start gap-2 text-xs sm:col-span-2">
            <input
              type="checkbox"
              checked={confirmed}
              onChange={(e) => setConfirmed(e.target.checked)}
              data-testid="finance-adjustment-confirmation"
            />
            <span>I confirm this audited credit/debit/adjustment is intentional and verified.</span>
          </label>
        </div>
        <button
          type="submit"
          className="min-h-11 rounded-xl bg-jp-accent px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
          disabled={!canSubmit}
          data-testid="finance-adjustment-store"
        >
          {busy === "store" ? "Posting…" : "Post adjustment"}
        </button>
      </form>

      <section className="rounded-xl border border-jp-border bg-white p-4" data-testid="finance-adjustment-list">
        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
          <h2 className="text-sm font-semibold text-gray-900">Recent manual adjustments</h2>
          <button
            type="button"
            className="rounded-lg border border-jp-border px-3 py-1 text-xs"
            disabled={busy !== null}
            onClick={() => void refreshList()}
          >
            Refresh
          </button>
        </div>
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead>
              <tr className="border-b border-jp-border text-left text-jp-muted">
                <th className="px-2 py-2">ID</th>
                <th className="px-2 py-2">Agency</th>
                <th className="px-2 py-2">Type</th>
                <th className="px-2 py-2">Amount</th>
                <th className="px-2 py-2">Reason</th>
                <th className="px-2 py-2">Reverse</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-2 py-4 text-jp-muted">
                    No manual adjustments yet.
                  </td>
                </tr>
              ) : (
                rows.map((row) => (
                  <tr key={row.id} className="border-b border-jp-border last:border-0" data-testid={`finance-adjustment-row-${row.id}`}>
                    <td className="px-2 py-2 font-medium">#{row.id}</td>
                    <td className="px-2 py-2">{row.agency_name ?? row.agency_id ?? "—"}</td>
                    <td className="px-2 py-2 capitalize">{row.type.replaceAll("_", " ")}</td>
                    <td className="px-2 py-2 tabular-nums">{formatCurrency(row.amount, row.currency ?? "PKR")}</td>
                    <td className="px-2 py-2">{row.adjustment_reason ? reasonLabel(String(row.adjustment_reason)) : "—"}</td>
                    <td className="px-2 py-2">
                      {row.can_reverse ? (
                        <div className="flex min-w-[14rem] flex-col gap-2">
                          <input
                            type="text"
                            className="rounded-lg border border-jp-border px-2 py-1 text-xs"
                            placeholder="Reversal reason"
                            value={reversalReasons[row.id] ?? ""}
                            onChange={(e) =>
                              setReversalReasons((current) => ({ ...current, [row.id]: e.target.value }))
                            }
                            data-testid={`finance-adjustment-reverse-reason-${row.id}`}
                          />
                          <button
                            type="button"
                            className="rounded-lg border border-red-200 bg-red-50 px-2 py-1 text-xs text-red-900 disabled:opacity-60"
                            disabled={busy !== null}
                            data-testid={`finance-adjustment-reverse-${row.id}`}
                            onClick={() => void onReverse(row)}
                          >
                            {busy === `reverse-${row.id}` ? "Reversing…" : "Reverse"}
                          </button>
                        </div>
                      ) : (
                        <span className="text-xs text-jp-muted">{row.is_reversal ? "Reversal entry" : "Not reversible"}</span>
                      )}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </section>

      {isLive ? (
        <section className="rounded-xl border border-jp-border bg-white p-4" data-testid="finance-export-links">
          <h2 className="text-sm font-semibold text-gray-900">Finance exports</h2>
          <p className="mt-1 text-xs text-jp-muted">
            Authorized Laravel CSV exports for reconciliation and the finance dashboard.
          </p>
          <div className="mt-3 flex flex-wrap gap-2">
            <a
              href={buildReconciliationExportHref()}
              className="inline-flex min-h-11 items-center rounded-xl border border-jp-border px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-50"
              data-testid="reconciliation-export-link"
            >
              Reconciliation export
            </a>
            <a
              href={buildFinanceDashboardExportHref()}
              className="inline-flex min-h-11 items-center rounded-xl border border-jp-border px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-50"
              data-testid="finance-dashboard-export-link"
            >
              Finance dashboard export
            </a>
          </div>
        </section>
      ) : null}

      <AgentStatementsWorkspace />
    </div>
  );
}
