"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useDashboardRouter } from "@/lib/dashboard-navigation";
import { Drawer } from "@/components/ui/drawer";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination } from "@/components/ui/pagination";
import { AuditActiveFilters } from "@/features/audit/components/audit-active-filters";
import { AuditDataTable } from "@/features/audit/components/audit-data-table";
import { AuditEventDetailDrawer } from "@/features/audit/components/audit-event-detail-drawer";
import { AuditExportPreview } from "@/features/audit/components/audit-export-preview";
import { AuditFilterBar } from "@/features/audit/components/audit-filter-bar";
import { AuditMobileCard } from "@/features/audit/components/audit-mobile-card";
import { AuditSecurityPanel } from "@/features/audit/components/audit-security-panel";
import { AuditSummaryMetrics } from "@/features/audit/components/audit-summary-metrics";
import { auditQueryToSearchParams } from "@/lib/audit-query";
import type { AuditModuleResult, AuditSortField } from "@/types/audit";

type Props = {
  result: AuditModuleResult;
};

export function AuditWorkspace({ result }: Props) {
  const router = useDashboardRouter();
  const [, startTransition] = useTransition();
  const [drawerDismissed, setDrawerDismissed] = useState(false);

  useEffect(() => {
    setDrawerDismissed(false);
  }, [result.query.selected]);

  const pushQuery = useCallback(
    (overrides: Partial<AuditModuleResult["query"]>) => {
      const next = { ...result.query, ...overrides };
      startTransition(() => {
        router.push(`/audit${auditQueryToSearchParams(next)}`);
      });
    },
    [result.query, router],
  );

  const onSort = (field: AuditSortField) => {
    const direction =
      result.query.sort === field && result.query.direction === "desc" ? "asc" : "desc";
    pushQuery({ sort: field, direction, page: 1 });
  };

  const onView = (id: string) => {
    pushQuery({ selected: id });
  };

  const onCloseDrawer = useCallback(() => {
    setDrawerDismissed(true);
    pushQuery({ selected: null });
  }, [pushQuery]);

  const drawerOpen = !drawerDismissed && Boolean(result.query.selected && result.selectedEvent);
  const empty = result.table.total === 0;
  const invalidDate = !result.dateRange.valid;

  return (
    <div data-testid="audit-workspace">
      <AuditSummaryMetrics summary={result.summary} invalidDate={invalidDate} />
      <div className="mt-4 space-y-3">
        <AuditFilterBar query={result.query} facets={result.facets} dateRange={result.dateRange} />
        <AuditActiveFilters query={result.query} dateRange={result.dateRange} />
      </div>

      <div className="mt-4 flex flex-wrap items-center gap-2">
        <AuditSecurityPanel
          active={result.query.securityView}
          securityEventCount={result.securityEventCount}
          onToggle={() => pushQuery({ securityView: !result.query.securityView, page: 1 })}
        />
        <AuditExportPreview manifest={result.exportManifest} events={result.exportEvents} filteredCount={result.table.total} />
      </div>

      {invalidDate ? (
        <p className="mt-4 text-sm text-jp-muted" role="status">
          Summary metrics are hidden until the date range is valid.
        </p>
      ) : null}

      {empty ? (
        <EmptyState
          title="No audit events match your filters"
          description="Try clearing filters or broadening your search. All data shown is synthetic preview data."
        />
      ) : (
        <>
          <div className="mt-4">
            <AuditDataTable
              rows={result.table.rows}
              sort={result.query.sort}
              direction={result.query.direction}
              onSort={onSort}
              onView={onView}
            />
            <AuditMobileCard rows={result.table.rows} onView={onView} />
          </div>
          <Pagination
            page={result.table.page}
            pageCount={result.table.pageCount}
            pageSize={result.table.pageSize}
            total={result.table.total}
            onPageChange={(page) => pushQuery({ page })}
            onPageSizeChange={(pageSize) => pushQuery({ pageSize, page: 1 })}
            ariaLabel="Audit pagination"
          />
        </>
      )}

      <Drawer
        open={drawerOpen}
        onClose={onCloseDrawer}
        title={result.selectedEvent ? result.selectedEvent.actionLabel : "Audit event details"}
        description={result.selectedEvent ? `${result.selectedEvent.id} · ${result.selectedEvent.category}` : undefined}
        closeAriaLabel="Close audit event details"
      >
        {result.selectedEvent ? <AuditEventDetailDrawer event={result.selectedEvent} /> : null}
      </Drawer>
    </div>
  );
}
