"use client";

import { useEffect, useState } from "react";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { Card, CardTitle } from "@/components/ui/card";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import { groupTicketingAdminPath } from "@/lib/api/portal-paths";

function payloadOf<T extends Record<string, unknown>>(result: { ok: boolean; data?: T } | T): T {
  if (result && typeof result === "object" && "data" in result && result.data) {
    return result.data as T;
  }
  return result as T;
}

export function GroupTicketingAdminWorkspace() {
  const isLive = useDashboardLiveMode();
  const [summary, setSummary] = useState<Record<string, unknown> | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!isLive) return;
    setLoading(true);
    setError(null);
    setSummary(null);
    void laravelRequest(groupTicketingAdminPath(), { method: "GET", retryCsrfOnce: false }).then((result) => {
      setLoading(false);
      if (!result.ok) {
        setError(result.message ?? "Could not load group ticketing admin.");
        return;
      }
      setSummary(payloadOf(result));
    });
  }, [isLive]);

  if (!isLive) {
    return (
      <PageContainer>
        <PageHeader title="Group Ticketing" description="Available in live dashboard mode only." />
      </PageContainer>
    );
  }

  return (
    <PageContainer>
      <PageHeader
        title="Group Ticketing Admin"
        description="Administrative inventory summary only. Public group booking engine is unchanged."
      />
      {error ? <p className="text-sm text-red-700">{error}</p> : null}
      {loading ? <p className="text-sm text-jp-muted">Loading group ticketing summary…</p> : null}
      {summary && !error ? (
        <div className="grid gap-3 sm:grid-cols-3">
          <Card>
            <div className="text-xs text-jp-muted">Active inventory</div>
            <CardTitle className="mt-1 text-2xl">{String(summary.activeInventoryCount ?? "—")}</CardTitle>
          </Card>
          <Card>
            <div className="text-xs text-jp-muted">Categories</div>
            <CardTitle className="mt-1 text-2xl">{String(summary.categoryCount ?? "—")}</CardTitle>
          </Card>
          <Card>
            <div className="text-xs text-jp-muted">Last sync</div>
            <CardTitle className="mt-1 text-base">{String(summary.lastSyncAt ?? "Never")}</CardTitle>
          </Card>
        </div>
      ) : null}
    </PageContainer>
  );
}
