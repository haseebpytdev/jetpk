"use client";

import { Button } from "@/components/ui/button";

export function OverviewToolbarActions() {
  return (
    <div className="flex flex-wrap gap-2">
      <Button variant="secondary" size="sm" type="button" disabled title="Refresh uses mock cache">
        Refresh
      </Button>
      <Button variant="secondary" size="sm" type="button" disabled>
        20 Jun, 2026
      </Button>
      <Button
        variant="primary"
        size="sm"
        type="button"
        onClick={() => alert("Export disabled in preview (NEXT_PUBLIC_ALLOW_MUTATIONS=false).")}
      >
        Export report
      </Button>
    </div>
  );
}
