"use client";

import { useEffect, useState } from "react";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { Card, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import { commissionsIndexPath } from "@/lib/api/portal-paths";
import { emptyListDescription } from "@/lib/empty-list-copy";
import { approveCommissionEntry, rejectCommissionEntry } from "@/services/operational-api";

type PendingEntry = {
  id: string;
  agent_id?: string;
  agent_name?: string | null;
  status?: string;
  commission_amount?: number;
  description?: string;
};

type CommissionsPayload = {
  ok?: boolean;
  kpis?: Record<string, number>;
  pending_entries?: PendingEntry[];
};

export function CommissionsWorkspace() {
  const isLive = useDashboardLiveMode();
  const [kpis, setKpis] = useState<Record<string, number>>({});
  const [entries, setEntries] = useState<PendingEntry[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);

  const refresh = async () => {
    const result = await laravelRequest<CommissionsPayload>(commissionsIndexPath(), {
      method: "GET",
      headers: { Accept: "application/json" },
      retryCsrfOnce: false,
    });
    if (!result.ok) {
      setError(result.message ?? "Could not load commissions.");
      return;
    }
    setError(null);
    setKpis(result.data.kpis ?? {});
    setEntries(Array.isArray(result.data.pending_entries) ? result.data.pending_entries : []);
  };

  useEffect(() => {
    if (!isLive) return;
    void refresh();
  }, [isLive]);

  if (!isLive) {
    return (
      <PageContainer>
        <PageHeader title="Commissions" description="Available in live dashboard mode only." />
      </PageContainer>
    );
  }

  async function act(id: string, kind: "approve" | "reject") {
    setBusy(`${kind}-${id}`);
    setError(null);
    const result =
      kind === "approve"
        ? await approveCommissionEntry(id)
        : await rejectCommissionEntry(id, "Rejected from dashboard commissions queue");
    setBusy(null);
    if (!result.ok) {
      setError(result.message ?? "Commission action failed.");
      return;
    }
    await refresh();
  }

  return (
    <PageContainer>
      <PageHeader
        title="Commissions"
        description="Pending commission queue from Laravel. Approve/reject only — no payout/adjustment mutations here."
        actions={
          <Button type="button" size="sm" variant="secondary" onClick={() => void refresh()}>
            Refresh
          </Button>
        }
      />
      <div className="grid gap-3 sm:grid-cols-4" data-testid="commissions-kpis">
        {["pending", "approved_unpaid", "paid_this_month", "active_agents"].map((key) => (
          <Card key={key}>
            <div className="text-xs text-jp-muted">{key}</div>
            <CardTitle className="mt-1 text-2xl">{String(kpis[key] ?? 0)}</CardTitle>
          </Card>
        ))}
      </div>
      {error ? (
        <p className="text-sm text-red-700" role="alert">
          {error}
        </p>
      ) : null}
      {entries.length === 0 && !error ? (
        <p className="text-sm text-jp-muted">{emptyListDescription(true, "Pending commission entries")}</p>
      ) : null}
      <ul className="space-y-3" data-testid="commissions-pending-list">
        {entries.map((entry) => (
          <li key={entry.id} className="rounded-xl border border-jp-border p-4 text-sm">
            <p className="font-medium">
              Entry {entry.id} · {entry.agent_name ?? `Agent ${entry.agent_id}`}
            </p>
            <p className="text-jp-muted">
              Amount: {entry.commission_amount ?? 0} · Status: {entry.status}
            </p>
            {entry.description ? <p className="mt-1 text-xs text-jp-muted">{entry.description}</p> : null}
            <div className="mt-3 flex flex-wrap gap-2">
              <Button
                type="button"
                size="sm"
                disabled={busy !== null}
                data-testid={`commission-approve-${entry.id}`}
                onClick={() => void act(entry.id, "approve")}
              >
                Approve
              </Button>
              <Button
                type="button"
                size="sm"
                variant="secondary"
                disabled={busy !== null}
                data-testid={`commission-reject-${entry.id}`}
                onClick={() => void act(entry.id, "reject")}
              >
                Reject
              </Button>
            </div>
          </li>
        ))}
      </ul>
    </PageContainer>
  );
}
