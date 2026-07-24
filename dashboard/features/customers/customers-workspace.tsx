"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Drawer } from "@/components/ui/drawer";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination } from "@/components/ui/pagination";
import { CustomerDetailDrawerContent } from "@/features/customers/customer-detail-drawer";
import { CustomersFilters } from "@/features/customers/customers-filters";
import { CustomersMobileCards } from "@/features/customers/customers-mobile-cards";
import { CustomersSummary } from "@/features/customers/customers-summary";
import { CustomersTable } from "@/features/customers/customers-table";
import { customersQueryToSearchParams } from "@/lib/customers-query";
import type { CustomerRecord, CustomerSortField, CustomersPageResult, CustomersQuery } from "@/types/customer";

type Props = {
  query: CustomersQuery;
  result: CustomersPageResult;
  selectedCustomer: CustomerRecord | null;
};

export function CustomersWorkspace({ query, result, selectedCustomer }: Props) {
  const router = useRouter();
  const [, startTransition] = useTransition();
  const [drawerDismissed, setDrawerDismissed] = useState(false);

  useEffect(() => {
    setDrawerDismissed(false);
  }, [query.selectedId]);

  const pushQuery = useCallback(
    (overrides: Partial<CustomersQuery>) => {
      const next = { ...query, ...overrides };
      startTransition(() => {
        router.push(`/customers${customersQueryToSearchParams(next)}`);
      });
    },
    [query, router],
  );

  const onSort = (field: CustomerSortField) => {
    const direction =
      query.sort === field && query.direction === "desc" ? "asc" : query.sort === field ? "desc" : "desc";
    pushQuery({ sort: field, direction, page: 1 });
  };

  const onView = (id: string) => {
    pushQuery({ selectedId: id });
  };

  const onCloseDrawer = useCallback(() => {
    setDrawerDismissed(true);
    pushQuery({ selectedId: null });
  }, [pushQuery]);

  const drawerOpen = !drawerDismissed && Boolean(query.selectedId && selectedCustomer);
  const empty = result.total === 0;

  return (
    <>
      <CustomersSummary summary={result.summary} />
      <CustomersFilters query={query} facets={result.facets} />

      {empty ? (
        <EmptyState
          title="No customers match your filters"
          description="Try clearing filters or broadening your search. All data shown is synthetic preview data."
        />
      ) : (
        <>
          <CustomersTable customers={result.customers} query={query} onSort={onSort} onView={onView} />
          <CustomersMobileCards customers={result.customers} onView={onView} />
          <Pagination
            page={result.page}
            pageCount={result.pageCount}
            pageSize={result.pageSize}
            total={result.total}
            onPageChange={(page) => pushQuery({ page })}
            onPageSizeChange={(pageSize) => pushQuery({ pageSize, page: 1 })}
            ariaLabel="Customers pagination"
          />
        </>
      )}

      <Drawer
        open={drawerOpen}
        onClose={onCloseDrawer}
        title={selectedCustomer ? selectedCustomer.fullName : "Customer details"}
        description={selectedCustomer ? `${selectedCustomer.id} · ${selectedCustomer.email}` : undefined}
        closeAriaLabel="Close customer details"
      >
        {selectedCustomer ? <CustomerDetailDrawerContent customer={selectedCustomer} /> : null}
      </Drawer>
    </>
  );
}
