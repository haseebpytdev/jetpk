"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { DateInput, SearchInput } from "@/components/ui/input";
import { Label } from "@/components/ui/page-layout";
import { Select } from "@/components/ui/select";
import { countActivePnrFilters } from "@/lib/pnrs-filter";
import { pnrsQueryToSearchParams } from "@/lib/pnrs-query";
import type { PnrsPageResult, PnrsQuery } from "@/types/pnr";

type Props = {
  query: PnrsQuery;
  facets: PnrsPageResult["facets"];
};

export function PnrsFilters({ query, facets }: Props) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [draft, setDraft] = useState(query);

  useEffect(() => {
    setDraft(query);
  }, [query]);

  const pushQuery = useCallback(
    (next: PnrsQuery) => {
      const href = `/pnrs${pnrsQueryToSearchParams(next)}`;
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
      referenceType: "all",
      channel: "all",
      supplier: "",
      airline: "",
      lifecycleStatus: "all",
      fulfilmentStatus: "all",
      ticketingStatus: "all",
      paymentStatus: "all",
      tripType: "all",
      hasAgent: "all",
      reviewRequired: "all",
      deadlineFrom: "",
      deadlineTo: "",
      departureFrom: "",
      departureTo: "",
      page: 1,
    });
    setDraft((d) => ({
      ...d,
      q: "",
      referenceType: "all",
      channel: "all",
      supplier: "",
      airline: "",
      lifecycleStatus: "all",
      fulfilmentStatus: "all",
      ticketingStatus: "all",
      paymentStatus: "all",
      tripType: "all",
      hasAgent: "all",
      reviewRequired: "all",
      deadlineFrom: "",
      deadlineTo: "",
      departureFrom: "",
      departureTo: "",
    }));
  };

  const activeCount = countActivePnrFilters(query);

  return (
    <Card className="space-y-4" data-testid="pnrs-filters">
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
          <Label htmlFor="pnrs-search">Search</Label>
          <SearchInput
            id="pnrs-search"
            placeholder="PNR ID, reference, booking, customer, route…"
            value={draft.q}
            onChange={(e) => setDraft((d) => ({ ...d, q: e.target.value }))}
            onClear={() => setDraft((d) => ({ ...d, q: "" }))}
            onKeyDown={(e) => {
              if (e.key === "Enter") apply();
            }}
          />
        </div>
        <div>
          <Label htmlFor="filter-reference-type">Reference type</Label>
          <Select
            id="filter-reference-type"
            value={draft.referenceType}
            onChange={(e) =>
              setDraft((d) => ({
                ...d,
                referenceType: e.target.value as PnrsQuery["referenceType"],
              }))
            }
          >
            <option value="all">All</option>
            {facets.referenceTypes.map((t) => (
              <option key={t} value={t}>
                {t}
              </option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-channel">Channel</Label>
          <Select
            id="filter-channel"
            value={draft.channel}
            onChange={(e) =>
              setDraft((d) => ({ ...d, channel: e.target.value as PnrsQuery["channel"] }))
            }
          >
            <option value="all">All</option>
            {facets.channels.map((c) => (
              <option key={c} value={c}>
                {c}
              </option>
            ))}
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
          <Label htmlFor="filter-lifecycle-status">Lifecycle status</Label>
          <Select
            id="filter-lifecycle-status"
            value={draft.lifecycleStatus}
            onChange={(e) =>
              setDraft((d) => ({
                ...d,
                lifecycleStatus: e.target.value as PnrsQuery["lifecycleStatus"],
              }))
            }
          >
            <option value="all">All</option>
            <option value="Active">Active</option>
            <option value="Confirmed">Confirmed</option>
            <option value="On Hold">On Hold</option>
            <option value="Pending Supplier">Pending Supplier</option>
            <option value="Partially Confirmed">Partially Confirmed</option>
            <option value="Cancelled">Cancelled</option>
            <option value="Expired">Expired</option>
            <option value="Failed">Failed</option>
            <option value="Review Required">Review Required</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-fulfilment-status">Fulfilment status</Label>
          <Select
            id="filter-fulfilment-status"
            value={draft.fulfilmentStatus}
            onChange={(e) =>
              setDraft((d) => ({
                ...d,
                fulfilmentStatus: e.target.value as PnrsQuery["fulfilmentStatus"],
              }))
            }
          >
            <option value="all">All</option>
            <option value="Not Required">Not Required</option>
            <option value="Pending">Pending</option>
            <option value="Partially Fulfilled">Partially Fulfilled</option>
            <option value="Fulfilled">Fulfilled</option>
            <option value="Failed">Failed</option>
            <option value="Refunded">Refunded</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-ticketing-status">Ticketing status</Label>
          <Select
            id="filter-ticketing-status"
            value={draft.ticketingStatus}
            onChange={(e) =>
              setDraft((d) => ({
                ...d,
                ticketingStatus: e.target.value as PnrsQuery["ticketingStatus"],
              }))
            }
          >
            <option value="all">All</option>
            <option value="Not Ticketed">Not Ticketed</option>
            <option value="Ready for Ticketing">Ready for Ticketing</option>
            <option value="Ticketing Blocked">Ticketing Blocked</option>
            <option value="Partially Ticketed">Partially Ticketed</option>
            <option value="Ticketed">Ticketed</option>
            <option value="Failed">Failed</option>
            <option value="Voided">Voided</option>
            <option value="Refunded">Refunded</option>
            <option value="Not Applicable">Not Applicable</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-payment-status">Payment status</Label>
          <Select
            id="filter-payment-status"
            value={draft.paymentStatus}
            onChange={(e) =>
              setDraft((d) => ({
                ...d,
                paymentStatus: e.target.value as PnrsQuery["paymentStatus"],
              }))
            }
          >
            <option value="all">All</option>
            <option value="Unpaid">Unpaid</option>
            <option value="Partially Paid">Partially Paid</option>
            <option value="Paid">Paid</option>
            <option value="Pending">Pending</option>
            <option value="Refunded">Refunded</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-trip-type">Trip type</Label>
          <Select
            id="filter-trip-type"
            value={draft.tripType}
            onChange={(e) =>
              setDraft((d) => ({ ...d, tripType: e.target.value as PnrsQuery["tripType"] }))
            }
          >
            <option value="all">All</option>
            <option value="one_way">One way</option>
            <option value="return">Return</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-has-agent">Has agent</Label>
          <Select
            id="filter-has-agent"
            value={draft.hasAgent}
            onChange={(e) =>
              setDraft((d) => ({ ...d, hasAgent: e.target.value as PnrsQuery["hasAgent"] }))
            }
          >
            <option value="all">All</option>
            <option value="yes">Has agent</option>
            <option value="no">No agent</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-review-required">Review required</Label>
          <Select
            id="filter-review-required"
            value={draft.reviewRequired}
            onChange={(e) =>
              setDraft((d) => ({
                ...d,
                reviewRequired: e.target.value as PnrsQuery["reviewRequired"],
              }))
            }
          >
            <option value="all">All</option>
            <option value="yes">Review required</option>
            <option value="no">No review</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="deadline-from">Deadline from</Label>
          <DateInput
            id="deadline-from"
            value={draft.deadlineFrom}
            onChange={(e) => setDraft((d) => ({ ...d, deadlineFrom: e.target.value }))}
          />
        </div>
        <div>
          <Label htmlFor="deadline-to">Deadline to</Label>
          <DateInput
            id="deadline-to"
            value={draft.deadlineTo}
            onChange={(e) => setDraft((d) => ({ ...d, deadlineTo: e.target.value }))}
          />
        </div>
        <div>
          <Label htmlFor="departure-from">Departure from</Label>
          <DateInput
            id="departure-from"
            value={draft.departureFrom}
            onChange={(e) => setDraft((d) => ({ ...d, departureFrom: e.target.value }))}
          />
        </div>
        <div>
          <Label htmlFor="departure-to">Departure to</Label>
          <DateInput
            id="departure-to"
            value={draft.departureTo}
            onChange={(e) => setDraft((d) => ({ ...d, departureTo: e.target.value }))}
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
