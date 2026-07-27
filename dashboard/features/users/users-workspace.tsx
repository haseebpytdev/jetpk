"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Drawer } from "@/components/ui/drawer";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination } from "@/components/ui/pagination";
import { UsersActiveFilters } from "@/features/users/components/users-active-filters";
import { UsersDataTable } from "@/features/users/components/users-data-table";
import { UserDetailDrawerContent } from "@/features/users/components/user-detail-drawer";
import { UsersFilterBar } from "@/features/users/components/users-filter-bar";
import { UserMobileCard } from "@/features/users/components/user-mobile-card";
import { UsersSummaryMetrics } from "@/features/users/components/users-summary-metrics";
import { usersQueryToSearchParams } from "@/lib/users-query";
import type { UserSortField, UsersModuleResult } from "@/types/users";

type Props = {
  result: UsersModuleResult;
};

export function UsersWorkspace({ result }: Props) {
  const router = useRouter();
  const [, startTransition] = useTransition();
  const [drawerDismissed, setDrawerDismissed] = useState(false);

  useEffect(() => {
    setDrawerDismissed(false);
  }, [result.query.selected]);

  const pushQuery = useCallback(
    (overrides: Partial<UsersModuleResult["query"]>) => {
      const next = { ...result.query, ...overrides };
      startTransition(() => {
        router.push(`/users${usersQueryToSearchParams(next)}`);
      });
    },
    [result.query, router],
  );

  const onSort = (field: UserSortField) => {
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

  const drawerOpen = !drawerDismissed && Boolean(result.query.selected && result.selectedUser);
  const empty = result.table.total === 0;
  const roleName = result.facets.roles.find((r) => r.id === result.query.role)?.name;

  return (
    <div data-testid="users-workspace">
      <UsersSummaryMetrics summary={result.summary} />
      <div className="mt-4 space-y-3">
        <UsersFilterBar query={result.query} facets={result.facets} />
        <UsersActiveFilters query={result.query} roleName={roleName} />
      </div>

      {empty ? (
        <EmptyState
          title="No users match your filters"
          description="Try clearing filters or broadening your search. All data shown is synthetic preview data."
        />
      ) : (
        <>
          <div className="mt-4">
            <UsersDataTable
              rows={result.table.rows}
              sort={result.query.sort}
              direction={result.query.direction}
              onSort={onSort}
              onView={onView}
            />
            <UserMobileCard users={result.table.rows} onView={onView} />
          </div>
          <Pagination
            page={result.table.page}
            pageCount={result.table.pageCount}
            pageSize={result.table.pageSize}
            total={result.table.total}
            onPageChange={(page) => pushQuery({ page })}
            onPageSizeChange={(pageSize) => pushQuery({ pageSize, page: 1 })}
            ariaLabel="Users pagination"
          />
        </>
      )}

      <Drawer
        open={drawerOpen}
        onClose={onCloseDrawer}
        title={result.selectedUser ? result.selectedUser.profile.fullName : "User details"}
        description={result.selectedUser ? `${result.selectedUser.id} · ${result.selectedUser.contact.email}` : undefined}
        closeAriaLabel="Close user details"
      >
        {result.selectedUser ? <UserDetailDrawerContent user={result.selectedUser} /> : null}
      </Drawer>
    </div>
  );
}
