"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useDashboardRouter } from "@/lib/dashboard-navigation";
import { Button } from "@/components/ui/button";
import { DateInput } from "@/components/ui/input";
import { Label } from "@/components/ui/page-layout";
import { Select } from "@/components/ui/select";
import { REPORT_SUPPORTED_CURRENCIES } from "@/lib/reports/constants";
import { REPORT_DATE_PRESET_LABELS } from "@/lib/reports/date-presets";
import { reportsQueryToSearchParams } from "@/lib/reports-query";
import type { ReportDatePreset, ReportFacets, ReportsQuery } from "@/types/report";

type Props = {
  query: ReportsQuery;
  facets: ReportFacets;
  modulePath: string;
  dateError?: string | null;
};

function countMoreActive(query: ReportsQuery): number {
  return (
    (query.comparison !== "none" ? 1 : 0) +
    (query.granularity !== "month" ? 1 : 0) +
    (query.supplier ? 1 : 0) +
    (query.airline ? 1 : 0) +
    (query.agent ? 1 : 0) +
    (query.route ? 1 : 0) +
    (query.bookingStatus !== "all" ? 1 : 0) +
    (query.paymentStatus !== "all" ? 1 : 0) +
    (query.ticketStatus !== "all" ? 1 : 0) +
    (query.fulfilmentStatus !== "all" ? 1 : 0) +
    (query.datePreset === "custom" && query.startDate ? 1 : 0) +
    (query.datePreset === "custom" && query.endDate ? 1 : 0)
  );
}

export function ReportFilters({ query, facets, modulePath, dateError }: Props) {
  const router = useDashboardRouter();
  const [pending, startTransition] = useTransition();
  const [draft, setDraft] = useState(query);
  const [moreOpen, setMoreOpen] = useState(false);

  useEffect(() => {
    setDraft(query);
  }, [query]);

  useEffect(() => {
    if (draft.datePreset === "custom") {
      setMoreOpen(true);
    }
  }, [draft.datePreset]);

  const pushQuery = useCallback(
    (next: ReportsQuery) => {
      const href = `/reports${modulePath}${reportsQueryToSearchParams(next)}`;
      startTransition(() => router.push(href));
    },
    [modulePath, router],
  );

  const apply = () => {
    pushQuery({ ...draft, page: 1 });
  };

  const reset = () => {
    const cleared: ReportsQuery = {
      ...query,
      datePreset: "current_year",
      startDate: "",
      endDate: "",
      comparison: "none",
      granularity: "month",
      currency: "PKR",
      channel: "",
      supplier: "",
      airline: "",
      agent: "",
      route: "",
      bookingStatus: "all",
      paymentStatus: "all",
      ticketStatus: "all",
      fulfilmentStatus: "all",
      page: 1,
      sort: "bookingDate",
      direction: "desc",
      previewError: false,
      previewLoading: false,
      previewEmpty: false,
    };
    setDraft(cleared);
    pushQuery(cleared);
  };

  const moreActive = countMoreActive(query);

  return (
    <div className="space-y-3 rounded-2xl border border-jp-border bg-white p-3 shadow-sm" data-testid="reports-filters">
      <div className="flex flex-col gap-3 lg:flex-row lg:items-end">
        <div className="w-full lg:w-48">
          <Label htmlFor="report-date-preset">Date preset</Label>
          <Select
            id="report-date-preset"
            value={draft.datePreset}
            onChange={(e) => setDraft((d) => ({ ...d, datePreset: e.target.value as ReportDatePreset }))}
          >
            {(Object.keys(REPORT_DATE_PRESET_LABELS) as ReportDatePreset[]).map((key) => (
              <option key={key} value={key}>
                {REPORT_DATE_PRESET_LABELS[key]}
              </option>
            ))}
          </Select>
        </div>
        <div className="w-full lg:w-36">
          <Label htmlFor="report-currency">Currency</Label>
          <Select
            id="report-currency"
            value={draft.currency}
            onChange={(e) => setDraft((d) => ({ ...d, currency: e.target.value as ReportsQuery["currency"] }))}
          >
            {REPORT_SUPPORTED_CURRENCIES.map((c) => (
              <option key={c} value={c}>
                {c}
              </option>
            ))}
          </Select>
        </div>
        <div className="w-full lg:w-44">
          <Label htmlFor="report-channel">Channel</Label>
          <Select
            id="report-channel"
            value={draft.channel}
            onChange={(e) => setDraft((d) => ({ ...d, channel: e.target.value }))}
          >
            {facets.channels.map((c) => (
              <option key={c.value || "all"} value={c.value}>
                {c.label}
              </option>
            ))}
          </Select>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={() => setMoreOpen((open) => !open)}
            aria-expanded={moreOpen}
            data-testid="reports-more-filters"
          >
            More filters{moreActive > 0 ? ` (${moreActive})` : ""}
          </Button>
          <Button type="button" variant="ghost" size="sm" onClick={reset}>
            Reset
          </Button>
          <Button type="button" size="sm" onClick={apply} disabled={pending} aria-busy={pending}>
            Apply
          </Button>
        </div>
      </div>

      {moreOpen ? (
        <div
          className="grid gap-3 rounded-xl border border-jp-border bg-gray-50 p-3 sm:grid-cols-2 xl:grid-cols-4"
          data-testid="reports-more-filters-panel"
        >
          {draft.datePreset === "custom" ? (
            <>
              <div>
                <Label htmlFor="report-start-date">Start date</Label>
                <DateInput
                  id="report-start-date"
                  value={draft.startDate}
                  onChange={(e) => setDraft((d) => ({ ...d, startDate: e.target.value }))}
                />
              </div>
              <div>
                <Label htmlFor="report-end-date">End date</Label>
                <DateInput
                  id="report-end-date"
                  value={draft.endDate}
                  onChange={(e) => setDraft((d) => ({ ...d, endDate: e.target.value }))}
                />
              </div>
            </>
          ) : null}
          <div>
            <Label htmlFor="report-comparison">Comparison</Label>
            <Select
              id="report-comparison"
              value={draft.comparison}
              onChange={(e) => setDraft((d) => ({ ...d, comparison: e.target.value as ReportsQuery["comparison"] }))}
            >
              <option value="none">No comparison</option>
              <option value="previous_period">Previous period</option>
              <option value="previous_year">Previous year</option>
            </Select>
          </div>
          <div>
            <Label htmlFor="report-granularity">Granularity</Label>
            <Select
              id="report-granularity"
              value={draft.granularity}
              onChange={(e) => setDraft((d) => ({ ...d, granularity: e.target.value as ReportsQuery["granularity"] }))}
            >
              <option value="day">Day</option>
              <option value="week">Week</option>
              <option value="month">Month</option>
              <option value="quarter">Quarter</option>
            </Select>
          </div>
          <div>
            <Label htmlFor="report-supplier">Supplier</Label>
            <Select
              id="report-supplier"
              value={draft.supplier}
              onChange={(e) => setDraft((d) => ({ ...d, supplier: e.target.value }))}
            >
              <option value="">All suppliers</option>
              {facets.suppliers.map((s) => (
                <option key={s} value={s}>
                  {s}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="report-airline">Airline</Label>
            <Select
              id="report-airline"
              value={draft.airline}
              onChange={(e) => setDraft((d) => ({ ...d, airline: e.target.value }))}
            >
              <option value="">All airlines</option>
              {facets.airlines.map((a) => (
                <option key={a} value={a}>
                  {a}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="report-agent">Agent</Label>
            <Select
              id="report-agent"
              value={draft.agent}
              onChange={(e) => setDraft((d) => ({ ...d, agent: e.target.value }))}
            >
              <option value="">All agents</option>
              {facets.agents.map((a) => (
                <option key={a} value={a}>
                  {a}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="report-route">Route</Label>
            <Select
              id="report-route"
              value={draft.route}
              onChange={(e) => setDraft((d) => ({ ...d, route: e.target.value }))}
            >
              <option value="">All routes</option>
              {facets.routes.map((r) => (
                <option key={r} value={r}>
                  {r}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="report-booking-status">Booking status</Label>
            <Select
              id="report-booking-status"
              value={draft.bookingStatus}
              onChange={(e) => setDraft((d) => ({ ...d, bookingStatus: e.target.value }))}
            >
              <option value="all">All</option>
              <option value="confirmed">Confirmed</option>
              <option value="pending">Pending</option>
              <option value="failed">Failed</option>
              <option value="cancelled">Cancelled</option>
            </Select>
          </div>
          <div>
            <Label htmlFor="report-payment-status">Payment status</Label>
            <Select
              id="report-payment-status"
              value={draft.paymentStatus}
              onChange={(e) => setDraft((d) => ({ ...d, paymentStatus: e.target.value }))}
            >
              <option value="all">All</option>
              <option value="paid">Paid</option>
              <option value="unpaid">Unpaid</option>
              <option value="partial">Partial</option>
              <option value="pending">Pending</option>
            </Select>
          </div>
          <div>
            <Label htmlFor="report-ticket-status">Ticket status</Label>
            <Select
              id="report-ticket-status"
              value={draft.ticketStatus}
              onChange={(e) => setDraft((d) => ({ ...d, ticketStatus: e.target.value }))}
            >
              <option value="all">All</option>
              <option value="issued">Issued</option>
              <option value="pending">Pending</option>
              <option value="void">Void</option>
            </Select>
          </div>
          <div>
            <Label htmlFor="report-fulfilment-status">Fulfilment status</Label>
            <Select
              id="report-fulfilment-status"
              value={draft.fulfilmentStatus}
              onChange={(e) => setDraft((d) => ({ ...d, fulfilmentStatus: e.target.value }))}
            >
              <option value="all">All</option>
              <option value="complete">Complete</option>
              <option value="pending">Pending</option>
              <option value="failed">Failed</option>
            </Select>
          </div>
        </div>
      ) : null}

      {dateError ? (
        <p className="text-sm text-red-700" role="alert" id="report-date-error">
          {dateError}
        </p>
      ) : null}
    </div>
  );
}
