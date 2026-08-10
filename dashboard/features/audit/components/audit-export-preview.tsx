"use client";

import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Drawer } from "@/components/ui/drawer";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { downloadAuditExportCsv } from "@/lib/audit/export-preview";
import type { AuditEvent } from "@/types/access-control";
import type { AuditExportManifest } from "@/types/audit";

type Props = {
  manifest: AuditExportManifest;
  events: AuditEvent[];
  filteredCount: number;
};

export function AuditExportPreview({ manifest, events, filteredCount }: Props) {
  const isLive = useDashboardLiveMode();
  const [open, setOpen] = useState(false);

  return (
    <>
      <Button
        type="button"
        variant="secondary"
        size="sm"
        onClick={() => setOpen(true)}
        data-testid="audit-export-button"
      >
        {isLive ? "Export CSV" : "Export preview CSV"}
      </Button>
      <Drawer
        open={open}
        onClose={() => setOpen(false)}
        title={isLive ? "Audit export" : "Audit export preview"}
        description={
          isLive
            ? "CSV export for the current filtered view. Approved columns only."
            : "Fixture-backed CSV export for the current filtered view. Approved columns only."
        }
        closeAriaLabel="Close audit export"
      >
        <div className="space-y-4 text-sm" data-testid="audit-export-preview">
          <p>
            <strong>{manifest.title}</strong>
          </p>
          <p className="text-jp-muted">Filtered events: {filteredCount}</p>
          <p className="text-jp-muted">Export rows: {manifest.rowCount}</p>
          <p className="text-jp-muted">Filename: {manifest.filename}</p>
          {isLive ? (
            <p className="text-jp-muted">
              Sensitive session identifiers, tokens, cookies, and complete IP addresses are excluded.
            </p>
          ) : (
            <p className="text-jp-muted">
              Preview data only — no session identifiers, tokens, cookies, or complete IP addresses are included.
            </p>
          )}
          <div>
            <p className="font-medium">Manifest columns</p>
            <ul className="mt-1 list-disc pl-5 text-xs text-jp-muted">
              {manifest.columns.map((col) => (
                <li key={col}>{col}</li>
              ))}
            </ul>
          </div>
          <Button
            type="button"
            onClick={() => {
              downloadAuditExportCsv(events);
              setOpen(false);
            }}
          >
            Download CSV
          </Button>
        </div>
      </Drawer>
    </>
  );
}
