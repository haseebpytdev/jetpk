"use client";

import { useEffect, useState } from "react";
import { OperationalExecutionWorkspace } from "@/features/execution/operational-execution-workspace";
import { PageHeader } from "@/components/ui/page-layout";
import {
  mockCancellationExecutions,
  mockRefundExecutions,
  mockTicketingExecutions,
} from "@/mocks/execution-fixtures";
import { resolveDataSourceMode } from "@/lib/read-only/data-source";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import { cancellationsIndexPath, refundsIndexPath } from "@/lib/api/portal-paths";
import type {
  CancellationExecutionRecord,
  RefundExecutionRecord,
  TicketingExecutionRecord,
} from "@/mocks/execution-fixtures";

export default function OperationalExecutionPage() {
  const mode = resolveDataSourceMode();
  const isLive = useDashboardLiveMode();
  const [cancellations, setCancellations] = useState<CancellationExecutionRecord[]>(
    mode === "fixture" ? mockCancellationExecutions : [],
  );
  const [refunds, setRefunds] = useState<RefundExecutionRecord[]>(
    mode === "fixture" ? mockRefundExecutions : [],
  );
  const [ticketing] = useState<TicketingExecutionRecord[]>(
    mode === "fixture" ? mockTicketingExecutions : [],
  );
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!isLive) return;
    let cancelled = false;
    async function load() {
      setLoading(true);
      const [cancelResult, refundResult] = await Promise.all([
        laravelRequest<{ cancellations?: Array<Record<string, unknown>> }>(
          cancellationsIndexPath("queue=execution"),
          { method: "GET", headers: { Accept: "application/json" }, retryCsrfOnce: false },
        ),
        laravelRequest<{ refunds?: Array<Record<string, unknown>> }>(
          refundsIndexPath("queue=execution"),
          { method: "GET", headers: { Accept: "application/json" }, retryCsrfOnce: false },
        ),
      ]);
      if (cancelled) return;
      setLoading(false);
      if (!cancelResult.ok || !refundResult.ok) {
        setError(
          (!cancelResult.ok ? cancelResult.message : null) ??
            (!refundResult.ok ? refundResult.message : null) ??
            "Could not load execution queues.",
        );
        return;
      }
      setCancellations(
        (cancelResult.data.cancellations ?? []).map((row) => ({
          id: String(row.id ?? ""),
          bookingId: String(row.booking_id ?? ""),
          status: String(row.status ?? ""),
          pnr: String(row.pnr ?? row.booking_reference ?? "—"),
          capabilities: (row.capabilities as CancellationExecutionRecord["capabilities"]) ?? null,
        })),
      );
      setRefunds(
        (refundResult.data.refunds ?? []).map((row) => ({
          id: String(row.id ?? ""),
          bookingId: String(row.booking_id ?? ""),
          status: String(row.status ?? ""),
          amount: Number(row.amount ?? 0),
          currency: String(row.currency ?? "PKR"),
          capabilities: (row.capabilities as RefundExecutionRecord["capabilities"]) ?? null,
        })),
      );
    }
    void load();
    return () => {
      cancelled = true;
    };
  }, [isLive]);

  return (
    <div className="space-y-6">
      <PageHeader
        title="Operational execution"
        description={
          isLive
            ? "Live Laravel-backed execution read queues. Process/issue/mark-paid only when lifecycle capability allows. Production UAT: read only — no supplier/payment mutations."
            : "Fixture execution controls for layout testing only."
        }
      />
      {loading ? <p className="text-sm text-jp-muted">Loading execution queues…</p> : null}
      {error ? (
        <p className="text-sm text-red-700" role="alert">
          {error}
        </p>
      ) : null}
      <OperationalExecutionWorkspace
        cancellations={cancellations}
        refunds={refunds}
        ticketing={ticketing}
      />
    </div>
  );
}
