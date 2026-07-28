"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useDashboardRouter } from "@/lib/dashboard-navigation";
import { Drawer } from "@/components/ui/drawer";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination } from "@/components/ui/pagination";
import { PermissionDetailDrawerContent } from "@/features/permissions/components/permission-detail-drawer";
import { PermissionMobileCard } from "@/features/permissions/components/permission-mobile-card";
import { PermissionsActiveFilters } from "@/features/permissions/components/permissions-active-filters";
import { PermissionsDataTable } from "@/features/permissions/components/permissions-data-table";
import { PermissionsFilterBar } from "@/features/permissions/components/permissions-filter-bar";
import { PermissionsSummaryMetrics } from "@/features/permissions/components/permissions-summary-metrics";
import { permissionsQueryToSearchParams } from "@/lib/permissions-query";
import type { PermissionSortField, PermissionsModuleResult } from "@/types/permissions";

type Props = {
  result: PermissionsModuleResult;
};

export function PermissionsWorkspace({ result }: Props) {
  const router = useDashboardRouter();
  const [, startTransition] = useTransition();
  const [drawerDismissed, setDrawerDismissed] = useState(false);

  useEffect(() => {
    setDrawerDismissed(false);
  }, [result.query.selected]);

  const pushQuery = useCallback(
    (overrides: Partial<PermissionsModuleResult["query"]>) => {
      const next = { ...result.query, ...overrides };
      startTransition(() => {
        router.push(`/users/permissions${permissionsQueryToSearchParams(next)}`);
      });
    },
    [result.query, router],
  );

  const onSort = (field: PermissionSortField) => {
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

  const drawerOpen = !drawerDismissed && Boolean(result.query.selected && result.selectedPermission);
  const empty = result.table.total === 0;

  return (
    <div data-testid="permissions-workspace">
      <PermissionsSummaryMetrics summary={result.summary} />
      <div className="mt-4 space-y-3">
        <PermissionsFilterBar query={result.query} facets={result.facets} />
        <PermissionsActiveFilters query={result.query} />
      </div>

      {empty ? (
        <EmptyState
          title="No permissions match your filters"
          description="Try clearing filters or broadening your search. All data shown is synthetic preview data."
        />
      ) : (
        <>
          <div className="mt-4">
            <PermissionsDataTable
              rows={result.table.rows}
              sort={result.query.sort}
              direction={result.query.direction}
              onSort={onSort}
              onView={onView}
            />
            <PermissionMobileCard permissions={result.table.rows} onView={onView} />
          </div>
          <Pagination
            page={result.table.page}
            pageCount={result.table.pageCount}
            pageSize={result.table.pageSize}
            total={result.table.total}
            onPageChange={(page) => pushQuery({ page })}
            onPageSizeChange={(pageSize) => pushQuery({ pageSize, page: 1 })}
            ariaLabel="Permissions pagination"
          />
        </>
      )}

      <Drawer
        open={drawerOpen}
        onClose={onCloseDrawer}
        title={result.selectedPermission ? result.selectedPermission.label : "Permission details"}
        description={result.selectedPermission ? `${result.selectedPermission.id} · ${result.selectedPermission.key}` : undefined}
        closeAriaLabel="Close permission details"
      >
        {result.selectedPermission ? (
          <PermissionDetailDrawerContent
            permission={result.selectedPermission}
            assignedRoles={result.assignedRoles}
            validationIssues={result.validationIssues}
          />
        ) : null}
      </Drawer>
    </div>
  );
}
