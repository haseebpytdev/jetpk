"use client";

import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Drawer } from "@/components/ui/drawer";
import { downloadReportCsv } from "@/lib/reports/export-download";
import type { ReportModuleResult } from "@/types/report";

export function ReportExportMenu({ result, allRows }: { result: ReportModuleResult; allRows: Record<string, string | number | null>[] }) {
  const [open, setOpen] = useState(false);

  return (
    <>
      <Button type="button" variant="secondary" size="sm" onClick={() => setOpen(true)} data-testid="reports-export-button">
        Export preview CSV
      </Button>
      <Drawer
        open={open}
        onClose={() => setOpen(false)}
        title="Export preview report"
        description="Fixture-backed CSV export for the current filtered view."
        closeAriaLabel="Close export summary"
      >
        <div className="space-y-4 text-sm">
          <p>
            <strong>{result.exportManifest.title}</strong>
          </p>
          <p className="text-jp-muted">
            Period: {result.dateRange.startDate} — {result.dateRange.endDate}
          </p>
          <p className="text-jp-muted">Rows: {allRows.length}</p>
          <p className="text-jp-muted">Reports are calculated from deterministic JetPakistan preview records.</p>
          <Button
            type="button"
            onClick={() => {
              downloadReportCsv(result, allRows);
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
