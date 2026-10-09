"use client";

import { useCallback, useTransition } from "react";
import { useDashboardRouter } from "@/lib/dashboard-navigation";
import { EmptyState } from "@/components/ui/empty-state";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { emptyListDescription } from "@/lib/empty-list-copy";
import { Pagination } from "@/components/ui/pagination";
import { PnrsFilters } from "@/features/pnrs/pnrs-filters";
import { PnrsMobileCards } from "@/features/pnrs/pnrs-mobile-cards";
import { PnrsSummary } from "@/features/pnrs/pnrs-summary";
import { PnrsTable } from "@/features/pnrs/pnrs-table";
import { pnrsQueryToSearchParams } from "@/lib/pnrs-query";
import { pnrHasLinkedBooking, pnrStandaloneDetailPath, pnrViewBookingPath } from "@/features/pnrs/pnr-booking-link";
import type { PnrRecord, PnrSortField, PnrsPageResult, PnrsQuery } from "@/types/pnr";

type Props = {
  query: PnrsQuery;
  result: PnrsPageResult;
};

export function PnrsWorkspace({ query, result }: Props) {
  const router = useDashboardRouter();
  const isLive = useDashboardLiveMode();
  const [, startTransition] = useTransition();

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

  const onView = (pnr: PnrRecord) => {
    if (pnrHasLinkedBooking(pnr)) {
      router.push(pnrViewBookingPath(pnr));
      return;
    }
    router.push(pnrStandaloneDetailPath(pnr));
  };

  const empty = result.total === 0;

  return (
    <>
      <PnrsSummary summary={result.summary} />
      <PnrsFilters query={query} facets={result.facets} />

      {empty ? (
        <EmptyState
          title="No PNRs or orders match your filters"
          description={emptyListDescription(isLive)}
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
    </>
  );
}
