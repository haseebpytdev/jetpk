"use client";

import { useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { Drawer } from "@/components/ui/drawer";
import { downloadReportCsv } from "@/lib/reports/export-download";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { buildReportsExportHref } from "@/services/operational-api";
import type { ReportModuleResult, ReportsModuleKey } from "@/types/report";

function laravelExportType(module: ReportsModuleKey): string {
  switch (module) {
    case "payments":
      return "payments";
    case "bookings":
      return "bookings";
    case "operations":
      return "supplier_diagnostics";
    case "sales":
    case "overview":
    default:
      return "sales";
  }
}

export function ReportExportMenu({ result, allRows }: { result: ReportModuleResult; allRows: Record<string, string | number | null>[] }) {
  const [open, setOpen] = useState(false);
  const isLive = useDashboardLiveMode();
  const laravelHref = useMemo(
    () =>
      buildReportsExportHref(laravelExportType(result.module), {
        startDate: result.dateRange.startDate,
        endDate: result.dateRange.endDate,
      }),
    [result.dateRange.endDate, result.dateRange.startDate, result.module],
  );

  return (
    <>
      <Button type="button" variant="secondary" size="sm" onClick={() => setOpen(true)} data-testid="reports-export-button">
        {isLive ? "Export CSV" : "Export preview CSV"}
      </Button>
      <Drawer
        open={open}
        onClose={() => setOpen(false)}
        title={isLive ? "Export report" : "Export preview report"}
        description={
          isLive
            ? "CSV export for the current filtered live report view."
            : "Fixture-backed CSV export for the current filtered view."
        }
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
          <p className="text-jp-muted">
            {isLive
              ? "Prefer the Laravel export for audited server-side CSV. Client CSV mirrors the filtered on-screen rows."
              : "Reports are calculated from deterministic JetPakistan preview records."}
          </p>
          {isLive ? (
            <a
              href={laravelHref}
              className="inline-flex min-h-11 items-center rounded-xl bg-jp-accent px-4 text-sm font-medium text-white"
              data-testid="reports-laravel-export-link"
            >
              Download Laravel CSV
            </a>
          ) : null}
          <Button
            type="button"
            variant={isLive ? "secondary" : "primary"}
            onClick={() => {
              downloadReportCsv(result, allRows);
              setOpen(false);
            }}
            data-testid="reports-client-export-button"
          >
            {isLive ? "Download filtered view CSV" : "Download CSV"}
          </Button>
        </div>
      </Drawer>
    </>
  );
}
