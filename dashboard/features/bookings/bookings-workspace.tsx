"use client";

import { useCallback, useEffect, useState } from "react";
import { useSearchParams } from "next/navigation";
import { useDashboardRouter } from "@/lib/dashboard-navigation";
import { useDashboardPortal } from "@/lib/portal-context";
import { dashboardHref, type DashboardPortal } from "@/lib/portal-path";
import { Drawer } from "@/components/ui/drawer";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination } from "@/components/ui/pagination";
import { BookingDetailDrawerContent } from "@/features/bookings/booking-detail-drawer";
import { BookingsFilters } from "@/features/bookings/bookings-filters";
import { BookingsMobileCards } from "@/features/bookings/bookings-mobile-cards";
import { BookingsSummary } from "@/features/bookings/bookings-summary";
import { BookingsTable } from "@/features/bookings/bookings-table";
import { bookingsQueryToSearchParams } from "@/lib/bookings-query";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import type { BookingRecord, BookingSortField, BookingsQuery, BookingsPageResult } from "@/types/booking";

type Props = {
  query: BookingsQuery;
  result: BookingsPageResult;
  selectedBooking: BookingRecord | null;
};

function syncBookingsUrl(portal: DashboardPortal, query: BookingsQuery) {
  const href = dashboardHref(portal, `/bookings${bookingsQueryToSearchParams(query)}`);
  window.history.replaceState(window.history.state, "", href);
}

export function BookingsWorkspace({ query, result, selectedBooking }: Props) {
  const router = useDashboardRouter();
  const portal = useDashboardPortal();
  const searchParams = useSearchParams();
  const [drawerDismissed, setDrawerDismissed] = useState(false);
  const [pendingDrawerId, setPendingDrawerId] = useState<string | null>(null);

  const urlSelectedId = searchParams.get("id")?.trim() || null;
  const resolvedBookingId = pendingDrawerId ?? urlSelectedId ?? query.selectedId;

  useEffect(() => {
    if (query.selectedId || urlSelectedId) {
      setDrawerDismissed(false);
    }
  }, [query.selectedId, urlSelectedId]);

  const pushQuery = useCallback(
    (overrides: Partial<BookingsQuery>) => {
      const next = { ...query, ...overrides };
      router.push(`/bookings${bookingsQueryToSearchParams(next)}`);
    },
    [query, router],
  );

  const onSort = (field: BookingSortField) => {
    const direction =
      query.sort === field && query.direction === "desc" ? "asc" : query.sort === field ? "desc" : "desc";
    pushQuery({ sort: field, direction, page: 1 });
  };

  const onCloseDrawer = useCallback(() => {
    setDrawerDismissed(true);
    setPendingDrawerId(null);
    syncBookingsUrl(portal, { ...query, selectedId: null });
  }, [portal, query]);

  const listBooking = resolvedBookingId
    ? result.bookings.find((booking) => booking.id === resolvedBookingId)
    : null;
  const drawerBooking = selectedBooking ?? listBooking ?? null;
  const drawerOpen = !drawerDismissed && Boolean(resolvedBookingId && drawerBooking);

  const isLive = useDashboardLiveMode();
  const empty = result.total === 0;

  return (
    <>
      <BookingsSummary summary={result.summary} />
      <BookingsFilters query={query} facets={result.facets} />

      {empty ? (
        <EmptyState
          title="No bookings match your filters"
          description={
            isLive
              ? "Try clearing filters or broadening your search."
              : "Try clearing filters or broadening your search. Preview mode uses fixture data."
          }
        />
      ) : (
        <>
          <BookingsTable bookings={result.bookings} query={query} onSort={onSort} />
          <BookingsMobileCards bookings={result.bookings} query={query} />
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
        title={drawerBooking ? drawerBooking.id : "Booking details"}
        description={drawerBooking ? `PNR ${drawerBooking.pnr}` : undefined}
        closeAriaLabel="Close booking details"
      >
        {drawerBooking ? <BookingDetailDrawerContent booking={drawerBooking} /> : null}
      </Drawer>
    </>
  );
}
