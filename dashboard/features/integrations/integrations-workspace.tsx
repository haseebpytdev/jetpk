"use client";

import { ApiConnectionsWorkspace } from "@/features/settings/components/api-connections-workspace";

/**
 * API & Modules — authoritative technical configuration workspace.
 * Landing shows configured connections only; provider catalog lives under Add Connection.
 */
export function IntegrationsWorkspace() {
  return (
    <div className="space-y-2" data-testid="api-modules-hub">
      <ApiConnectionsWorkspace showModuleChrome />
    </div>
  );
}
