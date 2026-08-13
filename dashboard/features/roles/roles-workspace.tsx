"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useDashboardRouter } from "@/lib/dashboard-navigation";
import { Drawer } from "@/components/ui/drawer";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination } from "@/components/ui/pagination";
import { RoleDetailDrawerContent } from "@/features/roles/components/role-detail-drawer";
import { RoleMobileCard } from "@/features/roles/components/role-mobile-card";
import { RolePermissionMatrix } from "@/features/roles/components/role-permission-matrix";
import { RolesActiveFilters } from "@/features/roles/components/roles-active-filters";
import { RolesDataTable } from "@/features/roles/components/roles-data-table";
import { RolesFilterBar } from "@/features/roles/components/roles-filter-bar";
import { RolesSummaryMetrics } from "@/features/roles/components/roles-summary-metrics";
import { rolesQueryToSearchParams } from "@/lib/roles-query";
import type { RoleSortField, RolesModuleResult } from "@/types/roles";

type Props = {
  result: RolesModuleResult;
};

export function RolesWorkspace({ result }: Props) {
  const router = useDashboardRouter();
  const [, startTransition] = useTransition();
  const [drawerDismissed, setDrawerDismissed] = useState(false);

  useEffect(() => {
    setDrawerDismissed(false);
  }, [result.query.selected]);

  const pushQuery = useCallback(
    (overrides: Partial<RolesModuleResult["query"]>) => {
      const next = { ...result.query, ...overrides };
      startTransition(() => {
        router.push(`/users/roles${rolesQueryToSearchParams(next)}`);
      });
    },
    [result.query, router],
  );

  const onSort = (field: RoleSortField) => {
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

  const drawerOpen = !drawerDismissed && Boolean(result.query.selected && result.selectedRole);
  const empty = result.table.total === 0;

  return (
    <div data-testid="roles-workspace">
      <p className="mb-3 text-sm text-jp-muted">
        Roles are the JetPakistan account-type catalogue (Platform Admin, Staff, Agent, Agent Staff, Customer). Permission
        assignment for staff is managed on the Staff/Users record. Custom Spatie role CRUD is not part of this domain.
      </p>
      <RolesSummaryMetrics summary={result.summary} />
      <div className="mt-4 space-y-3">
        <RolesFilterBar query={result.query} facets={result.facets} />
        <RolesActiveFilters query={result.query} />
      </div>

      {empty ? (
        <EmptyState
          title="No roles match your filters"
          description="Try clearing filters or broadening your search. All data shown is synthetic preview data."
        />
      ) : (
        <>
          <div className="mt-4">
            <RolesDataTable
              rows={result.table.rows}
              sort={result.query.sort}
              direction={result.query.direction}
              onSort={onSort}
              onView={onView}
            />
            <RoleMobileCard roles={result.table.rows} onView={onView} />
          </div>
          <Pagination
            page={result.table.page}
            pageCount={result.table.pageCount}
            pageSize={result.table.pageSize}
            total={result.table.total}
            onPageChange={(page) => pushQuery({ page })}
            onPageSizeChange={(pageSize) => pushQuery({ pageSize, page: 1 })}
            ariaLabel="Roles pagination"
          />
          <RolePermissionMatrix
            rows={result.table.rows}
            matrixDomain={result.query.matrixDomain}
            matrixRole={result.query.matrixRole}
            onDomainChange={(matrixDomain) => pushQuery({ matrixDomain })}
            onRoleChange={(matrixRole) => pushQuery({ matrixRole })}
          />
        </>
      )}

      <Drawer
        open={drawerOpen}
        onClose={onCloseDrawer}
        title={result.selectedRole ? result.selectedRole.name : "Role details"}
        description={result.selectedRole ? `${result.selectedRole.id} · ${result.selectedRole.key}` : undefined}
        closeAriaLabel="Close role details"
      >
        {result.selectedRole ? (
          <RoleDetailDrawerContent
            role={result.selectedRole}
            permissionKeys={result.selectedRolePermissionKeys}
            assignedUsers={result.selectedRoleAssignedUsers}
            validationIssues={result.validationIssues}
            compareA={result.query.compareA}
            compareB={result.query.compareB}
          />
        ) : null}
      </Drawer>
    </div>
  );
}
