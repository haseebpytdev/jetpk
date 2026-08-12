"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useDashboardRouter } from "@/lib/dashboard-navigation";
import { Button } from "@/components/ui/button";
import { DateInput } from "@/components/ui/input";
import { Label } from "@/components/ui/page-layout";
import { SearchInput } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { countActiveFilters } from "@/lib/bookings-filter";
import { bookingsQueryToSearchParams } from "@/lib/bookings-query";
import type { BookingsQuery } from "@/types/booking";
import type { BookingsPageResult } from "@/types/booking";

type Props = {
  query: BookingsQuery;
  facets: BookingsPageResult["facets"];
};

export function BookingsFilters({ query, facets }: Props) {
  const router = useDashboardRouter();
  const [pending, startTransition] = useTransition();
  const [draft, setDraft] = useState(query);
  const [moreOpen, setMoreOpen] = useState(false);

  useEffect(() => {
    setDraft(query);
  }, [query]);

  const pushQuery = useCallback(
    (next: BookingsQuery) => {
      const href = `/bookings${bookingsQueryToSearchParams(next)}`;
      startTransition(() => {
        router.push(href);
      });
    },
    [router],
  );

  const apply = () => {
    pushQuery({ ...draft, page: 1 });
  };

  const clearAll = () => {
    const cleared: BookingsQuery = {
      ...query,
      q: "",
      status: "all",
      payment: "all",
      ticketing: "all",
      supplier: "",
      airline: "",
      tripType: "all",
      bookingDateFrom: "",
      bookingDateTo: "",
      departureDateFrom: "",
      departureDateTo: "",
      page: 1,
    };
    setDraft(cleared);
    pushQuery(cleared);
  };

  const activeCount = countActiveFilters(query);
  const moreActive =
    (query.ticketing !== "all" ? 1 : 0) +
    (query.supplier ? 1 : 0) +
    (query.airline ? 1 : 0) +
    (query.tripType !== "all" ? 1 : 0) +
    (query.bookingDateFrom ? 1 : 0) +
    (query.bookingDateTo ? 1 : 0) +
    (query.departureDateFrom ? 1 : 0) +
    (query.departureDateTo ? 1 : 0);

  return (
    <div className="space-y-3 rounded-2xl border border-jp-border bg-white p-3 shadow-sm" data-testid="bookings-filters">
      <div className="flex flex-col gap-3 lg:flex-row lg:items-end">
        <div className="min-w-0 flex-1">
          <Label htmlFor="bookings-search">Search</Label>
          <SearchInput
            id="bookings-search"
            placeholder="ID, PNR, customer, route, airline…"
            value={draft.q}
            onChange={(e) => setDraft((d) => ({ ...d, q: e.target.value }))}
            onClear={() => setDraft((d) => ({ ...d, q: "" }))}
            onKeyDown={(e) => {
              if (e.key === "Enter") apply();
            }}
          />
        </div>
        <div className="w-full lg:w-44">
          <Label htmlFor="filter-status">Booking status</Label>
          <Select
            id="filter-status"
            value={draft.status}
            onChange={(e) => setDraft((d) => ({ ...d, status: e.target.value as BookingsQuery["status"] }))}
          >
            <option value="all">All</option>
            <option value="confirmed">Confirmed</option>
            <option value="pending">Pending</option>
            <option value="failed">Failed</option>
            <option value="cancelled">Cancelled</option>
          </Select>
        </div>
        <div className="w-full lg:w-44">
          <Label htmlFor="filter-payment">Payment status</Label>
          <Select
            id="filter-payment"
            value={draft.payment}
            onChange={(e) => setDraft((d) => ({ ...d, payment: e.target.value as BookingsQuery["payment"] }))}
          >
            <option value="all">All</option>
            <option value="paid">Paid</option>
            <option value="unpaid">Unpaid</option>
            <option value="partial">Partial</option>
            <option value="pending">Pending</option>
          </Select>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button
            variant="secondary"
            size="sm"
            type="button"
            aria-expanded={moreOpen}
            onClick={() => setMoreOpen((v) => !v)}
          >
            More filters{moreActive > 0 ? ` (${moreActive})` : ""}
          </Button>
          <Button variant="ghost" size="sm" type="button" onClick={clearAll} disabled={activeCount === 0}>
            Reset
          </Button>
          <Button size="sm" type="button" onClick={apply} disabled={pending} aria-busy={pending}>
            Apply
          </Button>
        </div>
      </div>

      {moreOpen ? (
        <div className="grid gap-3 border-t border-jp-border pt-3 sm:grid-cols-2 xl:grid-cols-4" data-testid="bookings-more-filters">
          <div>
            <Label htmlFor="filter-ticketing">Ticketing status</Label>
            <Select
              id="filter-ticketing"
              value={draft.ticketing}
              onChange={(e) =>
                setDraft((d) => ({ ...d, ticketing: e.target.value as BookingsQuery["ticketing"] }))
              }
            >
              <option value="all">All</option>
              <option value="ticketed">Ticketed</option>
              <option value="unticketed">Unticketed</option>
              <option value="pending">Pending</option>
            </Select>
          </div>
          <div>
            <Label htmlFor="filter-supplier">Supplier</Label>
            <Select
              id="filter-supplier"
              value={draft.supplier}
              onChange={(e) => setDraft((d) => ({ ...d, supplier: e.target.value }))}
            >
              <option value="">All</option>
              {facets.suppliers.map((s) => (
                <option key={s} value={s}>
                  {s}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="filter-airline">Airline</Label>
            <Select
              id="filter-airline"
              value={draft.airline}
              onChange={(e) => setDraft((d) => ({ ...d, airline: e.target.value }))}
            >
              <option value="">All</option>
              {facets.airlines.map((a) => (
                <option key={a} value={a}>
                  {a}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="filter-trip">Trip type</Label>
            <Select
              id="filter-trip"
              value={draft.tripType}
              onChange={(e) => setDraft((d) => ({ ...d, tripType: e.target.value as BookingsQuery["tripType"] }))}
            >
              <option value="all">All</option>
              <option value="one_way">One way</option>
              <option value="return">Return</option>
            </Select>
          </div>
          <div>
            <Label htmlFor="booking-from">Booking date from</Label>
            <DateInput
              id="booking-from"
              value={draft.bookingDateFrom}
              onChange={(e) => setDraft((d) => ({ ...d, bookingDateFrom: e.target.value }))}
            />
          </div>
          <div>
            <Label htmlFor="booking-to">Booking date to</Label>
            <DateInput
              id="booking-to"
              value={draft.bookingDateTo}
              onChange={(e) => setDraft((d) => ({ ...d, bookingDateTo: e.target.value }))}
            />
          </div>
          <div>
            <Label htmlFor="departure-from">Departure from</Label>
            <DateInput
              id="departure-from"
              value={draft.departureDateFrom}
              onChange={(e) => setDraft((d) => ({ ...d, departureDateFrom: e.target.value }))}
            />
          </div>
          <div>
            <Label htmlFor="departure-to">Departure to</Label>
            <DateInput
              id="departure-to"
              value={draft.departureDateTo}
              onChange={(e) => setDraft((d) => ({ ...d, departureDateTo: e.target.value }))}
            />
          </div>
        </div>
      ) : null}

      {activeCount > 0 ? (
        <p className="text-xs text-jp-muted">{activeCount} active filter{activeCount === 1 ? "" : "s"}</p>
      ) : null}
    </div>
  );
}
