"use client";

import { Button } from "@/components/ui/button";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";

export function OverviewToolbarActions() {
  const isLive = useDashboardLiveMode();
  return (
    <div className="flex flex-wrap gap-2">
      <Button
        variant="secondary"
        size="sm"
        type="button"
        disabled
        title={isLive ? "Refresh coming soon" : "Preview: refresh uses fixture cache"}
      >
        Refresh
      </Button>
      <Button variant="secondary" size="sm" type="button" disabled>
        {isLive ? "Live" : "20 Jun, 2026"}
      </Button>
      <Button
        variant="primary"
        size="sm"
        type="button"
        disabled={isLive}
        title={isLive ? "Export report is not enabled for this view yet" : undefined}
        onClick={() => {
          if (!isLive) {
            alert("Export disabled in preview (NEXT_PUBLIC_ALLOW_MUTATIONS=false).");
          }
        }}
      >
        Export report
      </Button>
    </div>
  );
}
