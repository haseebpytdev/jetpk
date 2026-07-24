"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Drawer } from "@/components/ui/drawer";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination } from "@/components/ui/pagination";
import { SupplierDetailDrawerContent } from "@/features/suppliers/supplier-detail-drawer";
import { SuppliersFilters } from "@/features/suppliers/suppliers-filters";
import { SuppliersMobileCards } from "@/features/suppliers/suppliers-mobile-cards";
import { SuppliersSummary } from "@/features/suppliers/suppliers-summary";
import { SuppliersTable } from "@/features/suppliers/suppliers-table";
import { suppliersQueryToSearchParams } from "@/lib/suppliers-query";
import type { SupplierRecord, SupplierSortField, SuppliersPageResult, SuppliersQuery } from "@/types/supplier";

type Props = {
  query: SuppliersQuery;
  result: SuppliersPageResult;
  selectedSupplier: SupplierRecord | null;
};

export function SuppliersWorkspace({ query, result, selectedSupplier }: Props) {
  const router = useRouter();
  const [, startTransition] = useTransition();
  const [drawerDismissed, setDrawerDismissed] = useState(false);

  useEffect(() => {
    setDrawerDismissed(false);
  }, [query.selectedId]);

  const pushQuery = useCallback(
    (overrides: Partial<SuppliersQuery>) => {
      const next = { ...query, ...overrides };
      startTransition(() => {
        router.push(`/suppliers${suppliersQueryToSearchParams(next)}`);
      });
    },
    [query, router],
  );

  const onSort = (field: SupplierSortField) => {
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

  const drawerOpen = !drawerDismissed && Boolean(query.selectedId && selectedSupplier);
  const empty = result.total === 0;

  return (
    <>
      <SuppliersSummary summary={result.summary} />
      <SuppliersFilters query={query} facets={result.facets} />

      {empty ? (
        <EmptyState
          title="No suppliers match your filters"
          description="Try clearing filters or broadening your search. All data shown is synthetic preview data."
        />
      ) : (
        <>
          <SuppliersTable suppliers={result.suppliers} query={query} onSort={onSort} onView={onView} />
          <SuppliersMobileCards suppliers={result.suppliers} onView={onView} />
          <Pagination
            page={result.page}
            pageCount={result.pageCount}
            pageSize={result.pageSize}
            total={result.total}
            onPageChange={(page) => pushQuery({ page })}
            onPageSizeChange={(pageSize) => pushQuery({ pageSize, page: 1 })}
            ariaLabel="Suppliers pagination"
          />
        </>
      )}

      <Drawer
        open={drawerOpen}
        onClose={onCloseDrawer}
        title={selectedSupplier ? selectedSupplier.supplierName : "Supplier details"}
        description={
          selectedSupplier
            ? `${selectedSupplier.id} · ${selectedSupplier.displayCode}`
            : undefined
        }
        closeAriaLabel="Close supplier details"
      >
        {selectedSupplier ? <SupplierDetailDrawerContent supplier={selectedSupplier} /> : null}
      </Drawer>
    </>
  );
}
