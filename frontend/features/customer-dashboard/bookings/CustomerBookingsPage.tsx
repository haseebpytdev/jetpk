"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { customerApiErrorMessage, fetchCustomerBookings } from "../services/customer-dashboard-api";
import {
  CustomerDashboardErrorState,
  CustomerDashboardShell,
  CustomerEmptyState,
  StatusBadge,
} from "../shell/CustomerDashboardShell";
import type { CustomerBookingListItem, PaginatedMeta } from "../types";
import type { PublicSession } from "@/types/session";

const FILTERS = [
  { value: "all", label: "All" },
  { value: "pending_payment", label: "Pending payment" },
  { value: "pnr_created", label: "PNR created" },
  { value: "needs_action", label: "Needs action" },
  { value: "cancelled", label: "Cancelled" },
] as const;

export function CustomerBookingsPage({ session }: { session: PublicSession }) {
  const [bookings, setBookings] = useState<CustomerBookingListItem[]>([]);
  const [pagination, setPagination] = useState<PaginatedMeta | null>(null);
  const [page, setPage] = useState(1);
  const [filter, setFilter] = useState("all");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = async (nextFilter = filter, nextPage = page) => {
    setLoading(true);
    setError(null);
    const result = await fetchCustomerBookings({ filter: nextFilter, page: nextPage });
    if (!result.ok) {
      setError(customerApiErrorMessage(result));
      setBookings([]);
      setPagination(null);
    } else {
      setBookings(result.data.bookings);
      setPagination(result.data.pagination);
    }
    setLoading(false);
  };

  useEffect(() => {
    void load(filter, page);
  }, [filter, page]);

  return (
    <CustomerDashboardShell session={session} title="My bookings">
      <div className="mb-4 flex flex-wrap gap-2" data-testid="customer-bookings-filters">
        {FILTERS.map((item) => (
          <button
            key={item.value}
            type="button"
            className={`rounded-jp-button border px-3 py-1.5 text-jp-sm focus-visible:shadow-jp-focus ${
              filter === item.value ? "border-jp-primary bg-jp-primary/10 text-jp-primary" : "border-jp-border"
            }`}
            onClick={() => {
              setFilter(item.value);
              setPage(1);
            }}
          >
            {item.label}
          </button>
        ))}
      </div>

      {loading ? <p className="text-jp-sm text-jp-muted">Loading bookings…</p> : null}
      {error ? <CustomerDashboardErrorState message={error} onRetry={() => load()} /> : null}

      {!loading && !error && bookings.length === 0 ? (
        <CustomerEmptyState title="No bookings found" description="Try another filter or search for flights." />
      ) : null}

      <div className="space-y-3" data-testid="customer-bookings-list">
        {bookings.map((booking) => (
          <article key={booking.booking_reference} className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 shadow-jp-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <Link href={booking.detail_url} className="text-jp-base font-semibold text-jp-primary">
                  {booking.booking_reference}
                </Link>
                <p className="mt-1 text-jp-sm text-jp-muted">
                  {booking.route} · {booking.departure_date ?? "Date TBC"}
                </p>
                <p className="text-jp-sm text-jp-muted">
                  {booking.passenger_count} passenger{booking.passenger_count === 1 ? "" : "s"} · {booking.currency}{" "}
                  {booking.total.toLocaleString()}
                </p>
              </div>
              <div className="flex flex-wrap gap-2">
                <StatusBadge status={booking.booking_status} />
                <StatusBadge status={booking.payment_status} />
                <StatusBadge status={booking.ticketing_status} />
              </div>
            </div>
            {booking.pnr ? <p className="mt-2 text-jp-xs text-jp-muted">PNR: {booking.pnr}</p> : null}
          </article>
        ))}
      </div>

      {pagination && pagination.last_page > 1 ? (
        <div className="mt-4 flex items-center justify-between gap-3" data-testid="customer-bookings-pagination">
          <button
            type="button"
            className="rounded-jp-button border border-jp-border px-3 py-1.5 text-jp-sm disabled:opacity-50"
            disabled={page <= 1}
            onClick={() => setPage((current) => Math.max(1, current - 1))}
          >
            Previous
          </button>
          <p className="text-jp-sm text-jp-muted">
            Page {pagination.current_page} of {pagination.last_page}
          </p>
          <button
            type="button"
            className="rounded-jp-button border border-jp-border px-3 py-1.5 text-jp-sm disabled:opacity-50"
            disabled={page >= pagination.last_page}
            onClick={() => setPage((current) => current + 1)}
          >
            Next
          </button>
        </div>
      ) : null}
    </CustomerDashboardShell>
  );
}
