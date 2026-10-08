"use client";

import { useState, useTransition } from "react";
import { Button } from "@/components/ui/button";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { useDashboardRouter } from "@/lib/dashboard-navigation";

export function OverviewToolbarActions() {
  const isLive = useDashboardLiveMode();
  const router = useDashboardRouter();
  const [pending, startTransition] = useTransition();
  const [previewStamp] = useState(() => "20 Jun, 2026");

  function onRefresh() {
    if (!isLive) {
      return;
    }
    startTransition(() => {
      router.refresh();
    });
  }

  return (
    <div className="flex flex-wrap gap-2" data-testid="overview-toolbar-actions">
      <Button
        variant="secondary"
        size="sm"
        type="button"
        disabled={!isLive || pending}
        onClick={onRefresh}
        data-testid="overview-refresh"
      >
        {pending ? "Refreshing…" : "Refresh"}
      </Button>
      {!isLive ? (
        <Button variant="secondary" size="sm" type="button" disabled>
          {previewStamp}
        </Button>
      ) : null}
    </div>
  );
}
