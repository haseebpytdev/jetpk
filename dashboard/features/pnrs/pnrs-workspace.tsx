"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useDashboardRouter } from "@/lib/dashboard-navigation";
import { Drawer } from "@/components/ui/drawer";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination } from "@/components/ui/pagination";
import { PnrDetailDrawerContent } from "@/features/pnrs/pnr-detail-drawer";
import { PnrsFilters } from "@/features/pnrs/pnrs-filters";
import { PnrsMobileCards } from "@/features/pnrs/pnrs-mobile-cards";
import { PnrsSummary } from "@/features/pnrs/pnrs-summary";
import { PnrsTable } from "@/features/pnrs/pnrs-table";
import { pnrsQueryToSearchParams } from "@/lib/pnrs-query";
import type { PnrRecord, PnrSortField, PnrsPageResult, PnrsQuery } from "@/types/pnr";

type Props = {
  query: PnrsQuery;
  result: PnrsPageResult;
  selectedPnr: PnrRecord | null;
};

export function PnrsWorkspace({ query, result, selectedPnr }: Props) {
  const router = useDashboardRouter();
  const [, startTransition] = useTransition();
  const [drawerDismissed, setDrawerDismissed] = useState(false);

  useEffect(() => {
    setDrawerDismissed(false);
  }, [query.selectedId]);

  const pushQuery = useCallback(
    (overrides: Partial<PnrsQuery>) => {
      const next = { ...query, ...overrides };
      startTransition(() => {
        router.push(`/pnrs${pnrsQueryToSearchParams(next)}`);
      });
    },
    [query, router],
  );

  const onSort = (field: PnrSortField) => {
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

  const drawerOpen = !drawerDismissed && Boolean(query.selectedId && selectedPnr);
  const empty = result.total === 0;

  return (
    <>
      <PnrsSummary summary={result.summary} />
      <PnrsFilters query={query} facets={result.facets} />

      {empty ? (
        <EmptyState
          title="No PNRs or orders match your filters"
          description="Try clearing filters or broadening your search. All data shown is synthetic preview data."
        />
      ) : (
        <>
          <PnrsTable pnrs={result.pnrs} query={query} onSort={onSort} onView={onView} />
          <PnrsMobileCards pnrs={result.pnrs} onView={onView} />
          <Pagination
            page={result.page}
            pageCount={result.pageCount}
            pageSize={result.pageSize}
            total={result.total}
            onPageChange={(page) => pushQuery({ page })}
            onPageSizeChange={(pageSize) => pushQuery({ pageSize, page: 1 })}
            ariaLabel="PNRs and orders pagination"
          />
        </>
      )}

      <Drawer
        open={drawerOpen}
        onClose={onCloseDrawer}
        title={selectedPnr ? selectedPnr.externalReference : "PNR / order details"}
        description={
          selectedPnr
            ? `${selectedPnr.id} · ${selectedPnr.referenceType} · ${selectedPnr.channel}`
            : undefined
        }
        closeAriaLabel="Close PNR details"
      >
        {selectedPnr ? <PnrDetailDrawerContent pnr={selectedPnr} /> : null}
      </Drawer>
    </>
  );
}
