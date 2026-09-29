"use client";

import { useEffect, useState } from "react";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { Card, CardTitle } from "@/components/ui/card";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import { goLiveChecklistPath } from "@/lib/api/portal-paths";

type ChecklistItem = { label?: string; note?: string; done?: boolean };

function payloadOf<T extends Record<string, unknown>>(result: { ok: boolean; data?: T } | T): T {
  if (result && typeof result === "object" && "data" in result && result.data) {
    return result.data as T;
  }
  return result as T;
}

export function GoLiveWorkspace() {
  const isLive = useDashboardLiveMode();
  const [items, setItems] = useState<ChecklistItem[]>([]);
  const [classification, setClassification] = useState("CURRENT_NEXT");
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!isLive) return;
    void laravelRequest(goLiveChecklistPath(), { method: "GET", retryCsrfOnce: false }).then((result) => {
      if (!result.ok) {
        setError(result.message ?? "Could not load go-live checklist.");
        return;
      }
      const payload = payloadOf(result);
      setItems(Array.isArray(payload.items) ? (payload.items as ChecklistItem[]) : []);
      setClassification(String(payload.classification ?? "CURRENT_NEXT"));
    });
  }, [isLive]);

  if (!isLive) {
    return (
      <PageContainer>
        <PageHeader title="Go-live" description="Available in live dashboard mode only." />
      </PageContainer>
    );
  }

  return (
    <PageContainer>
      <PageHeader
        title="Go-live checklist"
        description={`Classification: ${classification}. Read-only deployment readiness from current config.`}
      />
      {error ? <p className="text-sm text-red-700">{error}</p> : null}
      <div className="space-y-3">
        {items.map((item, index) => (
          <Card key={`${item.label}-${index}`}>
            <CardTitle>
              {item.done ? "Done" : "Pending"} — {item.label}
            </CardTitle>
            {item.note ? <p className="mt-1 text-sm text-jp-muted">{item.note}</p> : null}
          </Card>
        ))}
        {items.length === 0 ? <p className="text-sm text-jp-muted">No checklist items configured.</p> : null}
      </div>
    </PageContainer>
  );
}
