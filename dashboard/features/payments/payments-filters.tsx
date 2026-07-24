"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { DateInput, Input, SearchInput } from "@/components/ui/input";
import { Label } from "@/components/ui/page-layout";
import { Select } from "@/components/ui/select";
import { countActivePaymentFilters } from "@/lib/payments-filter";
import { paymentsQueryToSearchParams } from "@/lib/payments-query";
import type { PaymentsPageResult, PaymentsQuery } from "@/types/payment";

type Props = {
  query: PaymentsQuery;
  facets: PaymentsPageResult["facets"];
};

export function PaymentsFilters({ query, facets }: Props) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [draft, setDraft] = useState(query);

  useEffect(() => {
    setDraft(query);
  }, [query]);

  const pushQuery = useCallback(
    (next: PaymentsQuery) => {
      const href = `/payments${paymentsQueryToSearchParams(next)}`;
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
      paymentStatus: "all",
      transactionStatus: "all",
      type: "all",
      method: "all",
      channel: "all",
      reconciliation: "all",
      currency: "",
      dateFrom: "",
      dateTo: "",
      minAmount: "",
      maxAmount: "",
      page: 1,
    });
    setDraft((d) => ({
      ...d,
      q: "",
      paymentStatus: "all",
      transactionStatus: "all",
      type: "all",
      method: "all",
      channel: "all",
      reconciliation: "all",
      currency: "",
      dateFrom: "",
      dateTo: "",
      minAmount: "",
      maxAmount: "",
    }));
  };

  const activeCount = countActivePaymentFilters(query);

  return (
    <Card className="space-y-4" data-testid="payments-filters">
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
          <Label htmlFor="payments-search">Search</Label>
          <SearchInput
            id="payments-search"
            placeholder="Transaction, payment, booking, PNR, customer, references…"
            value={draft.q}
            onChange={(e) => setDraft((d) => ({ ...d, q: e.target.value }))}
            onClear={() => setDraft((d) => ({ ...d, q: "" }))}
            onKeyDown={(e) => {
              if (e.key === "Enter") apply();
            }}
          />
        </div>
        <div>
          <Label htmlFor="filter-payment-status">Payment status</Label>
          <Select
            id="filter-payment-status"
            value={draft.paymentStatus}
            onChange={(e) =>
              setDraft((d) => ({ ...d, paymentStatus: e.target.value as PaymentsQuery["paymentStatus"] }))
            }
          >
            <option value="all">All</option>
            <option value="paid">Paid</option>
            <option value="unpaid">Unpaid</option>
            <option value="partial">Partial</option>
            <option value="pending">Pending</option>
            <option value="failed">Failed</option>
            <option value="reversed">Reversed</option>
            <option value="refunded">Refunded</option>
            <option value="partially_refunded">Partially refunded</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-transaction-status">Transaction status</Label>
          <Select
            id="filter-transaction-status"
            value={draft.transactionStatus}
            onChange={(e) =>
              setDraft((d) => ({
                ...d,
                transactionStatus: e.target.value as PaymentsQuery["transactionStatus"],
              }))
            }
          >
            <option value="all">All</option>
            <option value="succeeded">Succeeded</option>
            <option value="failed">Failed</option>
            <option value="pending">Pending</option>
            <option value="cancelled">Cancelled</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-type">Transaction type</Label>
          <Select
            id="filter-type"
            value={draft.type}
            onChange={(e) => setDraft((d) => ({ ...d, type: e.target.value as PaymentsQuery["type"] }))}
          >
            <option value="all">All</option>
            <option value="payment">Payment</option>
            <option value="refund">Refund</option>
            <option value="reversal">Reversal</option>
            <option value="fee">Fee</option>
            <option value="adjustment">Adjustment</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-method">Payment method</Label>
          <Select
            id="filter-method"
            value={draft.method}
            onChange={(e) => setDraft((d) => ({ ...d, method: e.target.value as PaymentsQuery["method"] }))}
          >
            <option value="all">All</option>
            {facets.methods.map((m) => (
              <option key={m} value={m}>
                {m.replace(/_/g, " ")}
              </option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-channel">Payment channel</Label>
          <Select
            id="filter-channel"
            value={draft.channel}
            onChange={(e) => setDraft((d) => ({ ...d, channel: e.target.value as PaymentsQuery["channel"] }))}
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
          <Label htmlFor="filter-reconciliation">Reconciliation</Label>
          <Select
            id="filter-reconciliation"
            value={draft.reconciliation}
            onChange={(e) =>
              setDraft((d) => ({
                ...d,
                reconciliation: e.target.value as PaymentsQuery["reconciliation"],
              }))
            }
          >
            <option value="all">All</option>
            <option value="reconciled">Reconciled</option>
            <option value="unreconciled">Unreconciled</option>
            <option value="disputed">Disputed</option>
            <option value="pending_review">Pending review</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-currency">Currency</Label>
          <Select
            id="filter-currency"
            value={draft.currency}
            onChange={(e) => setDraft((d) => ({ ...d, currency: e.target.value }))}
          >
            <option value="">All</option>
            {facets.currencies.map((c) => (
              <option key={c} value={c}>
                {c}
              </option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="date-from">Transaction date from</Label>
          <DateInput
            id="date-from"
            value={draft.dateFrom}
            onChange={(e) => setDraft((d) => ({ ...d, dateFrom: e.target.value }))}
          />
        </div>
        <div>
          <Label htmlFor="date-to">Transaction date to</Label>
          <DateInput
            id="date-to"
            value={draft.dateTo}
            onChange={(e) => setDraft((d) => ({ ...d, dateTo: e.target.value }))}
          />
        </div>
        <div>
          <Label htmlFor="min-amount">Min amount</Label>
          <Input
            id="min-amount"
            type="number"
            min={0}
            placeholder="0"
            value={draft.minAmount}
            onChange={(e) => setDraft((d) => ({ ...d, minAmount: e.target.value }))}
          />
        </div>
        <div>
          <Label htmlFor="max-amount">Max amount</Label>
          <Input
            id="max-amount"
            type="number"
            min={0}
            placeholder="Any"
            value={draft.maxAmount}
            onChange={(e) => setDraft((d) => ({ ...d, maxAmount: e.target.value }))}
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
