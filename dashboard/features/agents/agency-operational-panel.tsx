"use client";

import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";

/**
 * Hardcoded agency/application id "1" mutation buttons are retired.
 * Live agency application approve/reject must use a Laravel-backed applications list.
 */
export function AgencyOperationalPanel() {
  const isLive = useDashboardLiveMode();

  if (!isLive) {
    return (
      <p className="text-xs text-jp-muted" data-testid="agency-ops-preview">
        Agency operational actions are available in live dashboard mode only.
      </p>
    );
  }

  return (
    <div className="space-y-2 rounded-xl border border-jp-border p-4" data-testid="agency-operational-panel">
      <h2 className="text-sm font-semibold text-gray-900">Agency administration</h2>
      <p className="text-sm text-jp-muted" data-testid="agency-ops-gated">
        Placeholder mutations against fixed ids are disabled. Agent application review will use the live
        applications queue once that list contract is restored.
      </p>
    </div>
  );
}
