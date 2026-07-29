"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { fetchDepositCreateForm, submitAgentDeposit } from "../services/agent-dashboard-api";
import { AgentDashboardErrorState, AgentDashboardShell } from "../shell/AgentDashboardShell";
import type { WalletSummary } from "../types";
import type { PublicSession } from "@/types/session";

const fieldClass =
  "mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2 focus-visible:outline-none focus-visible:shadow-jp-focus";

function formatMoney(amount: number, currency: string) {
  return `${currency} ${amount.toLocaleString()}`;
}

export function NewDepositPage({ session }: { session: PublicSession }) {
  const router = useRouter();
  const [summary, setSummary] = useState<WalletSummary | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  const load = async () => {
    setLoading(true);
    setError(null);
    const result = await fetchDepositCreateForm();
    if (!result.ok) {
      setError(result.message);
    } else {
      setSummary(result.data.summary);
    }
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (submitting) return;
    setSubmitting(true);
    setError(null);
    setFieldErrors({});

    const formData = new FormData(event.currentTarget);
    const result = await submitAgentDeposit(formData);

    if (!result.ok) {
      setError(result.message);
      if (result.errors) setFieldErrors(result.errors);
    } else if (result.data.redirect_url) {
      router.push(result.data.redirect_url);
    } else {
      router.push("/agent/deposits");
    }
    setSubmitting(false);
  };

  return (
    <AgentDashboardShell session={session} title="New deposit request">
      <div className="mb-4">
        <Link href="/agent/deposits" className="text-jp-sm text-jp-primary focus-visible:shadow-jp-focus">
          Back to deposits
        </Link>
      </div>

      {loading ? <p className="text-jp-sm text-jp-muted">Loading form…</p> : null}
      {error ? <AgentDashboardErrorState message={error} onRetry={load} /> : null}

      {summary ? (
        <p className="mb-4 text-jp-sm text-jp-muted">Current balance: {formatMoney(summary.balance, summary.currency)}</p>
      ) : null}

      {!loading && !error ? (
        <form
          onSubmit={handleSubmit}
          encType="multipart/form-data"
          className="max-w-xl space-y-4 rounded-jp-lg border border-jp-border bg-jp-surface p-6"
          data-testid="agent-new-deposit-form"
        >
          <label className="block text-jp-sm">
            Amount
            <input name="amount" type="number" step="0.01" min="1" className={fieldClass} required />
            {fieldErrors.amount?.[0] ? <p className="mt-1 text-jp-xs text-red-700">{fieldErrors.amount[0]}</p> : null}
          </label>
          <label className="block text-jp-sm">
            Payment method
            <input name="payment_method" className={fieldClass} placeholder="Bank transfer, cash, etc." />
            {fieldErrors.payment_method?.[0] ? (
              <p className="mt-1 text-jp-xs text-red-700">{fieldErrors.payment_method[0]}</p>
            ) : null}
          </label>
          <label className="block text-jp-sm">
            Reference number
            <input name="reference" className={fieldClass} placeholder="Transaction or receipt reference" />
            {fieldErrors.reference?.[0] ? <p className="mt-1 text-jp-xs text-red-700">{fieldErrors.reference[0]}</p> : null}
          </label>
          <label className="block text-jp-sm">
            Note
            <textarea name="agent_note" rows={3} className={fieldClass} placeholder="Optional note for finance team" />
            {fieldErrors.agent_note?.[0] ? <p className="mt-1 text-jp-xs text-red-700">{fieldErrors.agent_note[0]}</p> : null}
          </label>
          <label className="block text-jp-sm">
            Payment proof
            <input
              name="proof"
              type="file"
              accept=".jpg,.jpeg,.png,.pdf,.webp,image/jpeg,image/png,application/pdf,image/webp"
              className="mt-1 block w-full text-jp-sm"
            />
            <p className="mt-1 text-jp-xs text-jp-muted">JPG, PNG, PDF or WebP up to 5 MB</p>
            {fieldErrors.proof?.[0] ? <p className="mt-1 text-jp-xs text-red-700">{fieldErrors.proof[0]}</p> : null}
          </label>
          <PrimaryButton type="submit" disabled={submitting}>
            {submitting ? "Submitting…" : "Submit deposit request"}
          </PrimaryButton>
        </form>
      ) : null}
    </AgentDashboardShell>
  );
}
