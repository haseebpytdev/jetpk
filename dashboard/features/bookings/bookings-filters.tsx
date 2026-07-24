"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
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
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [draft, setDraft] = useState(query);

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
    pushQuery({
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
    });
    setDraft((d) => ({
      ...d,
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
    }));
  };

  const activeCount = countActiveFilters(query);

  return (
    <Card className="space-y-4" data-testid="bookings-filters">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-sm font-semibold text-gray-900">Filters</h2>
        <div className="flex flex-wrap items-center gap-2 text-sm">
          {activeCount > 0 ? (
            <span className="rounded-full bg-jp-accent/10 px-2.5 py-1 text-xs font-medium text-jp-accent-muted">
              {activeCount} active
            </span>
          ) : null}
          <Button variant="ghost" size="sm" type="button" onClick={clearAll} disabled={activeCount === 0}>
            Clear all
          </Button>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div className="sm:col-span-2 xl:col-span-2">
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
        <div>
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
        <div>
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

      <div className="flex flex-wrap gap-2">
        <Button type="button" onClick={apply} disabled={pending} aria-busy={pending}>
          Apply filters
        </Button>
      </div>
    </Card>
  );
}
