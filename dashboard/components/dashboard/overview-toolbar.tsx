"use client";

import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";

export function OverviewToolbarActions() {
  const isLive = useDashboardLiveMode();
  const periodLabel = new Date().toLocaleDateString("en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });

  if (isLive) {
    return (
      <div className="flex flex-wrap items-center gap-2 text-sm text-jp-muted" data-testid="overview-period-label">
        <span className="rounded-lg border border-jp-border bg-white px-3 py-1.5 font-medium text-jp-text">
          {periodLabel}
        </span>
        <span className="text-xs">Live operational snapshot</span>
      </div>
    );
  }

  return (
    <div className="flex flex-wrap gap-2">
      <span className="rounded-lg border border-jp-border px-3 py-1.5 text-sm text-jp-muted">Preview period</span>
    </div>
  );
}
