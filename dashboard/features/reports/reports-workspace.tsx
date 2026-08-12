"use client";

import { useCallback, useTransition } from "react";
import { useDashboardRouter } from "@/lib/dashboard-navigation";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination } from "@/components/ui/pagination";
import { ReportActiveFilters } from "@/features/reports/components/report-active-filters";
import { ReportAttentionQueue } from "@/features/reports/components/report-attention-queue";
import { ReportBarList, ReportDonutChart, ReportTimeSeriesChart } from "@/features/reports/components/report-charts";
import { ReportDataTable } from "@/features/reports/components/report-data-table";
import { ReportExportMenu } from "@/features/reports/components/report-export-menu";
import { ReportFilters } from "@/features/reports/components/report-filters";
import { ReportMetricGrid } from "@/features/reports/components/report-metric-card";
import { Card, CardDescription, CardTitle } from "@/components/ui/card";
import { reportsQueryToSearchParams } from "@/lib/reports-query";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import type { ReportModuleResult } from "@/types/report";

const MODULE_PATHS: Record<ReportModuleResult["module"], string> = {
  overview: "",
  sales: "/sales",
  bookings: "/bookings",
  payments: "/payments",
  operations: "/operations",
};

type Props = {
  result: ReportModuleResult;
};

export function ReportsWorkspace({ result }: Props) {
  const router = useDashboardRouter();
  const isLive = useDashboardLiveMode();
  const [, startTransition] = useTransition();
  const modulePath = MODULE_PATHS[result.module];
  const dateError = result.validation.valid ? null : result.validation.issues[0]?.message ?? null;

  const pushQuery = useCallback(
    (overrides: Partial<ReportModuleResult["query"]>) => {
      const next = { ...result.query, ...overrides };
      startTransition(() => {
        router.push(`/reports${modulePath}${reportsQueryToSearchParams(next)}`);
      });
    },
    [modulePath, result.query, router],
  );

  const onSort = (key: string) => {
    const direction = result.query.sort === key && result.query.direction === "desc" ? "asc" : "desc";
    pushQuery({ sort: key, direction, page: 1 });
  };

  const allRows = result.exportRows;

  return (
    <div className="space-y-4">
      <ReportFilters query={result.query} facets={result.facets} modulePath={modulePath} dateError={dateError} />
      <ReportActiveFilters query={result.query} />

      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-sm text-jp-muted">
          {isLive
            ? "Reports are calculated from live JetPakistan booking and payment records."
            : "Reports are calculated from deterministic JetPakistan preview records."}
        </p>
        <ReportExportMenu result={result} allRows={allRows} />
      </div>

      {result.state === "empty" || !result.validation.valid ? (
        <EmptyState
          title={result.validation.valid ? "No report data for current filters" : "Invalid report date range"}
          description={
            result.validation.valid
              ? "Adjust date preset or filters to view report metrics, or reset filters to restore the default view."
              : (result.validation.issues[0]?.message ?? "Check the custom start and end dates.")
          }
        />
      ) : (
        <>
          <section aria-labelledby="reports-kpi-heading">
            <h2 id="reports-kpi-heading" className="text-sm font-semibold text-gray-900">
              Key metrics
            </h2>
            <div className="mt-3">
              <ReportMetricGrid metrics={result.metrics} />
            </div>
          </section>

          {result.module === "overview" && result.series.booking_value && result.series.payment_collection ? (
            <div className="grid gap-4 lg:grid-cols-2">
              <ReportTimeSeriesChart series={result.series.booking_value} description="Gross booking value over the selected period." />
              <ReportTimeSeriesChart series={result.series.payment_collection} description="Collected payments over the selected period." />
              <ReportDonutChart title="Booking status distribution" description="Bookings grouped by status." chartKey="booking_status" segments={result.charts.booking_status ?? []} />
              <ReportDonutChart title="Channel mix" description="Direct versus agent-assisted bookings." chartKey="channel" segments={result.charts.channel ?? []} />
              <ReportBarList title="Top routes" description="Gross booking value by route." chartKey="route" segments={result.charts.route ?? []} />
              <ReportBarList title="Supplier exposure" description="Supplier-linked booking exposure." chartKey="supplier" segments={result.charts.supplier ?? []} />
              <ReportDonutChart title="Fulfilment status" description="PNR/order fulfilment states." chartKey="fulfilment" segments={result.charts.fulfilment ?? []} />
            </div>
          ) : null}

          {result.module === "sales" && result.series.booking_value ? (
            <div className="grid gap-4 lg:grid-cols-2">
              <ReportTimeSeriesChart series={result.series.booking_value} description="Sales by period." />
              <ReportBarList title="Sales by route" description="Route performance." chartKey="route" segments={result.charts.route ?? []} />
              <ReportBarList title="Sales by airline" description="Airline contribution." chartKey="airline" segments={breakdownToSegments(result.breakdowns.airline ?? [])} />
              <ReportBarList title="Sales by supplier" description="Supplier-linked sales." chartKey="supplier" segments={result.charts.supplier ?? []} />
              <ReportBarList title="Sales by agent" description="Agent-assisted sales." chartKey="agent" segments={breakdownToSegments(result.breakdowns.agent ?? [])} />
              <ReportDonutChart title="Sales by channel" description="Channel distribution." chartKey="channel" segments={result.charts.channel ?? []} />
            </div>
          ) : null}

          {result.module === "bookings" && result.series.booking_value ? (
            <div className="grid gap-4 lg:grid-cols-2">
              <ReportDonutChart title="Booking lifecycle distribution" description="Booking status breakdown." chartKey="booking_status" segments={result.charts.booking_status ?? []} />
              <ReportTimeSeriesChart series={result.series.booking_value} description="Booking trend over time." />
              <ReportBarList title="Route distribution" description="Bookings by route." chartKey="route" segments={result.charts.route ?? []} />
              <ReportBarList title="Supplier distribution" description="Bookings by supplier." chartKey="supplier" segments={result.charts.supplier ?? []} />
              <ReportDonutChart title="Trip type" description="One-way versus return." chartKey="trip_type" segments={breakdownToSegments(result.breakdowns.trip_type ?? [])} />
              <ReportDonutChart title="Cabin distribution" description="Cabin mix from linked PNR records." chartKey="cabin" segments={breakdownToSegments(result.breakdowns.cabin ?? [])} />
              <ReportBarList title="Lead-time bands" description="Days between booking and departure." chartKey="lead_time" segments={breakdownToSegments(result.breakdowns.lead_time ?? [])} />
            </div>
          ) : null}

          {result.module === "payments" && result.series.payment_collection ? (
            <div className="grid gap-4 lg:grid-cols-2">
              <ReportTimeSeriesChart series={result.series.payment_collection} description="Payment collection trend." />
              <ReportDonutChart title="Payment status distribution" description="Transaction payment statuses." chartKey="payment_status" segments={result.charts.payment_status ?? []} />
              <ReportDonutChart title="Payment method distribution" description="Methods used in range." chartKey="payment_method" segments={result.charts.payment_method ?? []} />
              <ReportBarList title="Outstanding by agent" description="Agent-linked outstanding exposure." chartKey="agent" segments={breakdownToSegments(result.breakdowns.agent ?? [])} />
            </div>
          ) : null}

          {result.module === "operations" ? (
            <>
              <Card data-testid="operations-limitations">
                <CardTitle>Operational boundaries</CardTitle>
                <CardDescription className="mt-2 space-y-2">
                  {result.limitationNotices.map((notice) => (
                    <p key={notice}>{notice}</p>
                  ))}
                </CardDescription>
              </Card>
              <div className="grid gap-4 lg:grid-cols-2">
                <ReportDonutChart title="GDS versus NDC versus other" description="Sabre GDS, Sabre NDC, One API, Manual and Mock remain distinct." chartKey="pnr_channel" segments={result.charts.pnr_channel ?? []} />
                <ReportDonutChart title="PNR/order lifecycle" description="Lifecycle status distribution." chartKey="lifecycle" segments={breakdownToSegments(result.breakdowns.lifecycle ?? [])} />
                <ReportDonutChart title="Ticket/document status" description="Ticketing states are informational." chartKey="ticketing" segments={result.charts.ticketing ?? []} />
                <ReportDonutChart title="Fulfilment status" description="Fulfilment progress by PNR/order." chartKey="fulfilment" segments={result.charts.fulfilment ?? []} />
                <ReportDonutChart title="Cancellation eligibility" description="Informational only — no cancellation execution." chartKey="cancellation" segments={breakdownToSegments(result.breakdowns.cancellation ?? [])} />
              </div>
            </>
          ) : null}

          {result.funnel.length > 0 && (result.module === "bookings" || result.module === "overview") ? (
            <Card data-testid="reports-funnel">
              <CardTitle>Booking lifecycle funnel</CardTitle>
              <CardDescription className="mt-1">
                {isLive
                  ? "Reduced funnel based on live booking statuses — search/quote stages are not fabricated."
                  : "Reduced funnel based on available preview statuses — search/quote stages are not fabricated."}
              </CardDescription>
              <ol className="mt-4 space-y-2">
                {result.funnel.map((stage) => (
                  <li key={stage.id} className="flex items-center justify-between rounded-lg border border-jp-border px-3 py-2 text-sm">
                    <div>
                      <p className="font-medium">{stage.label}</p>
                      <p className="text-jp-muted">{stage.description}</p>
                    </div>
                    <span className="text-lg font-semibold tabular-nums">{stage.count}</span>
                  </li>
                ))}
              </ol>
            </Card>
          ) : null}

          {result.module === "overview" ? <ReportAttentionQueue items={result.attentionQueue} /> : null}

          {result.table.columns.length > 0 ? (
            <section aria-labelledby="reports-table-heading">
              <h2 id="reports-table-heading" className="mb-3 text-sm font-semibold text-gray-900">
                Detail table
              </h2>
              <ReportDataTable
                table={result.table}
                onSort={onSort}
                sort={result.query.sort}
                direction={result.query.direction}
                mobileTitle={`${result.module} row`}
              />
              <Pagination
                page={result.table.page}
                pageCount={result.table.pageCount}
                pageSize={result.table.pageSize}
                total={result.table.total}
                onPageChange={(page) => pushQuery({ page })}
                onPageSizeChange={(pageSize) => pushQuery({ pageSize, page: 1 })}
              />
            </section>
          ) : null}
        </>
      )}
    </div>
  );
}

function breakdownToSegments(rows: { id: string; label: string; value: number; formattedValue: string; sharePercent: number | null }[]) {
  const colors = ["#10B981", "#3B82F6", "#8B5CF6", "#F59E0B", "#EF4444", "#06B6D4", "#EC4899", "#6B7280"];
  return rows.map((row, index) => ({
    id: row.id,
    label: row.label,
    value: row.value,
    formattedValue: row.formattedValue,
    color: colors[index % colors.length]!,
    sharePercent: row.sharePercent,
  }));
}
