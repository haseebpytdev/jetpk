"use client";

import { useCallback, useTransition } from "react";
import { useDashboardRouter } from "@/lib/dashboard-navigation";
import { EmptyState } from "@/components/ui/empty-state";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { emptyListDescription } from "@/lib/empty-list-copy";
import { Pagination } from "@/components/ui/pagination";
import { BookingsFilters } from "@/features/bookings/bookings-filters";
import { BookingsMobileCards } from "@/features/bookings/bookings-mobile-cards";
import { BookingsSummary } from "@/features/bookings/bookings-summary";
import { BookingsTable } from "@/features/bookings/bookings-table";
import { bookingsQueryToSearchParams } from "@/lib/bookings-query";
import type { BookingSortField, BookingsQuery, BookingsPageResult } from "@/types/booking";

type Props = {
  query: BookingsQuery;
  result: BookingsPageResult;
};

export function BookingsWorkspace({ query, result }: Props) {
  const router = useDashboardRouter();
  const isLive = useDashboardLiveMode();
  const [, startTransition] = useTransition();

  const pushQuery = useCallback(
    (overrides: Partial<BookingsQuery>) => {
      const next = { ...query, ...overrides };
      startTransition(() => {
        router.push(`/bookings${bookingsQueryToSearchParams(next)}`);
      });
    },
    [query, router],
  );

  const onSort = (field: BookingSortField) => {
    const direction =
      query.sort === field && query.direction === "desc" ? "asc" : query.sort === field ? "desc" : "desc";
    pushQuery({ sort: field, direction, page: 1 });
  };

  const onView = (id: string) => {
    router.push(`/bookings/${encodeURIComponent(id)}`);
  };

  const empty = result.total === 0;

  return (
    <>
      <BookingsSummary summary={result.summary} />
      <BookingsFilters query={query} facets={result.facets} />

      {empty ? (
        <EmptyState
          title="No bookings match your filters"
          description={emptyListDescription(isLive)}
        />
      ) : (
        <>
          <BookingsTable
            bookings={result.bookings}
            query={query}
            onSort={onSort}
            onView={onView}
          />
          <BookingsMobileCards bookings={result.bookings} onView={onView} />
          <Pagination
            page={result.page}
            pageCount={result.pageCount}
            pageSize={result.pageSize}
            total={result.total}
            onPageChange={(page) => pushQuery({ page })}
            onPageSizeChange={(pageSize) => pushQuery({ pageSize, page: 1 })}
          />
        </>
      )}
    </>
  );
}
