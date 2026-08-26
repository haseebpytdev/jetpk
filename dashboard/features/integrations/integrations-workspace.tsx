"use client";

import { ApiConnectionsWorkspace } from "@/features/settings/components/api-connections-workspace";

/**
 * API & Modules — authoritative technical configuration workspace.
 * Landing shows configured connections only; provider catalog lives under Add Connection.
 * Operational Supplier Management remains available as an in-workspace link (not a competing sidebar entry).
 */
export function IntegrationsWorkspace() {
  return (
    <div className="space-y-4" data-testid="api-modules-hub">
      <div className="flex flex-wrap items-center gap-2 border-b border-jp-border pb-3" data-testid="api-modules-workspace-tabs">
        <span className="rounded-full border border-jp-green bg-jp-green/10 px-3 py-1 text-xs font-medium text-jp-green">
          Connections
        </span>
        <a
          href="/admin/dashboard/suppliers"
          className="rounded-full border border-jp-border bg-white px-3 py-1 text-xs font-medium text-jp-muted hover:text-jp-ink"
          data-testid="api-modules-suppliers-subsection"
        >
          Supplier operations
        </a>
      </div>
      <ApiConnectionsWorkspace showModuleChrome />
    </div>
  );
}
