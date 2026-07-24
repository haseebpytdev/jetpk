"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Drawer } from "@/components/ui/drawer";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination } from "@/components/ui/pagination";
import { BookingDetailDrawerContent } from "@/features/bookings/booking-detail-drawer";
import { BookingsFilters } from "@/features/bookings/bookings-filters";
import { BookingsMobileCards } from "@/features/bookings/bookings-mobile-cards";
import { BookingsSummary } from "@/features/bookings/bookings-summary";
import { BookingsTable } from "@/features/bookings/bookings-table";
import { bookingsQueryToSearchParams } from "@/lib/bookings-query";
import type { BookingRecord, BookingSortField, BookingsQuery, BookingsPageResult } from "@/types/booking";

type Props = {
  query: BookingsQuery;
  result: BookingsPageResult;
  selectedBooking: BookingRecord | null;
};

export function BookingsWorkspace({ query, result, selectedBooking }: Props) {
  const router = useRouter();
  const [, startTransition] = useTransition();
  const [drawerDismissed, setDrawerDismissed] = useState(false);

  useEffect(() => {
    setDrawerDismissed(false);
  }, [query.selectedId]);

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
    pushQuery({ selectedId: id });
  };

  const onCloseDrawer = useCallback(() => {
    setDrawerDismissed(true);
    pushQuery({ selectedId: null });
  }, [pushQuery]);

  const drawerOpen = !drawerDismissed && Boolean(query.selectedId && selectedBooking);

  const empty = result.total === 0;

  return (
    <>
      <BookingsSummary summary={result.summary} />
      <BookingsFilters query={query} facets={result.facets} />

      {empty ? (
        <EmptyState
          title="No bookings match your filters"
          description="Try clearing filters or broadening your search. All data shown is synthetic preview data."
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

      <Drawer
        open={drawerOpen}
        onClose={onCloseDrawer}
        title={selectedBooking ? selectedBooking.id : "Booking details"}
        description={selectedBooking ? `PNR ${selectedBooking.pnr}` : undefined}
        closeAriaLabel="Close booking details"
      >
        {selectedBooking ? <BookingDetailDrawerContent booking={selectedBooking} /> : null}
      </Drawer>
    </>
  );
}
