"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { DateInput, SearchInput } from "@/components/ui/input";
import { Label } from "@/components/ui/page-layout";
import { Select } from "@/components/ui/select";
import { countActiveTicketFilters } from "@/lib/tickets-filter";
import { ticketsQueryToSearchParams } from "@/lib/tickets-query";
import type { TicketsPageResult, TicketsQuery } from "@/types/ticket";

type Props = {
  query: TicketsQuery;
  facets: TicketsPageResult["facets"];
};

export function TicketsFilters({ query, facets }: Props) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [draft, setDraft] = useState(query);

  useEffect(() => {
    setDraft(query);
  }, [query]);

  const pushQuery = useCallback(
    (next: TicketsQuery) => {
      const href = `/tickets${ticketsQueryToSearchParams(next)}`;
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
      documentType: "all",
      channel: "all",
      airline: "",
      supplier: "",
      issueStatus: "all",
      fulfilmentStatus: "all",
      paymentStatus: "all",
      refundEligibility: "all",
      voidStatus: "all",
      hasAgent: "all",
      travelFrom: "",
      travelTo: "",
      issueFrom: "",
      issueTo: "",
      page: 1,
    });
    setDraft((d) => ({
      ...d,
      q: "",
      documentType: "all",
      channel: "all",
      airline: "",
      supplier: "",
      issueStatus: "all",
      fulfilmentStatus: "all",
      paymentStatus: "all",
      refundEligibility: "all",
      voidStatus: "all",
      hasAgent: "all",
      travelFrom: "",
      travelTo: "",
      issueFrom: "",
      issueTo: "",
    }));
  };

  const activeCount = countActiveTicketFilters(query);

  return (
    <Card className="space-y-4" data-testid="tickets-filters">
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
          <Label htmlFor="tickets-search">Search</Label>
          <SearchInput
            id="tickets-search"
            placeholder="Ticket ID, masked number, traveller, booking, PNR…"
            value={draft.q}
            onChange={(e) => setDraft((d) => ({ ...d, q: e.target.value }))}
            onClear={() => setDraft((d) => ({ ...d, q: "" }))}
            onKeyDown={(e) => {
              if (e.key === "Enter") apply();
            }}
          />
        </div>
        <div>
          <Label htmlFor="filter-document-type">Document type</Label>
          <Select
            id="filter-document-type"
            value={draft.documentType}
            onChange={(e) =>
              setDraft((d) => ({
                ...d,
                documentType: e.target.value as TicketsQuery["documentType"],
              }))
            }
          >
            <option value="all">All</option>
            {facets.documentTypes.map((t) => (
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
              setDraft((d) => ({ ...d, channel: e.target.value as TicketsQuery["channel"] }))
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
          <Label htmlFor="filter-issue-status">Issue status</Label>
          <Select
            id="filter-issue-status"
            value={draft.issueStatus}
            onChange={(e) =>
              setDraft((d) => ({
                ...d,
                issueStatus: e.target.value as TicketsQuery["issueStatus"],
              }))
            }
          >
            <option value="all">All</option>
            <option value="Pending">Pending</option>
            <option value="Issued">Issued</option>
            <option value="Partially Issued">Partially Issued</option>
            <option value="Blocked">Blocked</option>
            <option value="Failed">Failed</option>
            <option value="Voided">Voided</option>
            <option value="Refunded">Refunded</option>
            <option value="Not Applicable">Not Applicable</option>
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
                fulfilmentStatus: e.target.value as TicketsQuery["fulfilmentStatus"],
              }))
            }
          >
            <option value="all">All</option>
            <option value="Pending">Pending</option>
            <option value="Fulfilled">Fulfilled</option>
            <option value="Partially Fulfilled">Partially Fulfilled</option>
            <option value="Failed">Failed</option>
            <option value="Cancelled">Cancelled</option>
            <option value="Refunded">Refunded</option>
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
                paymentStatus: e.target.value as TicketsQuery["paymentStatus"],
              }))
            }
          >
            <option value="all">All</option>
            <option value="Unpaid">Unpaid</option>
            <option value="Partially Paid">Partially Paid</option>
            <option value="Paid">Paid</option>
            <option value="Refunded">Refunded</option>
            <option value="Partially Refunded">Partially Refunded</option>
            <option value="Reconciliation Required">Reconciliation Required</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-refund-eligibility">Refund eligibility</Label>
          <Select
            id="filter-refund-eligibility"
            value={draft.refundEligibility}
            onChange={(e) =>
              setDraft((d) => ({
                ...d,
                refundEligibility: e.target.value as TicketsQuery["refundEligibility"],
              }))
            }
          >
            <option value="all">All</option>
            <option value="Eligible">Eligible</option>
            <option value="Not Eligible">Not Eligible</option>
            <option value="Airline Review Required">Airline Review Required</option>
            <option value="Fare Rules Required">Fare Rules Required</option>
            <option value="Already Refunded">Already Refunded</option>
            <option value="Unknown">Unknown</option>
            <option value="Not Applicable">Not Applicable</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-void-status">Void status</Label>
          <Select
            id="filter-void-status"
            value={draft.voidStatus}
            onChange={(e) =>
              setDraft((d) => ({
                ...d,
                voidStatus: e.target.value as TicketsQuery["voidStatus"],
              }))
            }
          >
            <option value="all">All</option>
            <option value="Within Window">Within Window</option>
            <option value="Window Expired">Window Expired</option>
            <option value="Voided">Voided</option>
            <option value="Not Applicable">Not Applicable</option>
            <option value="Unknown">Unknown</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-has-agent">Has agent</Label>
          <Select
            id="filter-has-agent"
            value={draft.hasAgent}
            onChange={(e) =>
              setDraft((d) => ({
                ...d,
                hasAgent: e.target.value as TicketsQuery["hasAgent"],
              }))
            }
          >
            <option value="all">All</option>
            <option value="yes">Has agent</option>
            <option value="no">No agent</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="travel-from">Travel from</Label>
          <DateInput
            id="travel-from"
            value={draft.travelFrom}
            onChange={(e) => setDraft((d) => ({ ...d, travelFrom: e.target.value }))}
          />
        </div>
        <div>
          <Label htmlFor="travel-to">Travel to</Label>
          <DateInput
            id="travel-to"
            value={draft.travelTo}
            onChange={(e) => setDraft((d) => ({ ...d, travelTo: e.target.value }))}
          />
        </div>
        <div>
          <Label htmlFor="issue-from">Issue from</Label>
          <DateInput
            id="issue-from"
            value={draft.issueFrom}
            onChange={(e) => setDraft((d) => ({ ...d, issueFrom: e.target.value }))}
          />
        </div>
        <div>
          <Label htmlFor="issue-to">Issue to</Label>
          <DateInput
            id="issue-to"
            value={draft.issueTo}
            onChange={(e) => setDraft((d) => ({ ...d, issueTo: e.target.value }))}
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
