import { buildCsvContent } from "@/lib/csv-safe";
import { formatCurrency, formatDate, tripTypeLabel } from "@/lib/format";
import { aggregateReportMetrics, getOperationalFixtureGraph } from "@/lib/reports/aggregations";
import { buildAttentionQueue } from "@/lib/reports/attention-queue";
import { breakdownToChartSegments, buildBreakdownRows, countBreakdown } from "@/lib/reports/breakdowns";
import { enrichMetricsWithComparison } from "@/lib/reports/comparison";
import { REPORT_REFERENCE_DATE } from "@/lib/reports/constants";
import { resolveComparisonPeriod, resolveDatePreset, validateCustomDateRange } from "@/lib/reports/date-presets";
import {
  bookingAgentName,
  bookingChannel,
  bookingRoute,
  buildReportFacets,
  filteredGraph,
} from "@/lib/reports/query-filters";
import { buildTimeSeries } from "@/lib/reports/series-builder";
import { sumSameCurrencyAmounts } from "@/lib/reports/currency";
import type {
  ReportDateRange,
  ReportExportManifest,
  ReportFunnelStage,
  ReportMetric,
  ReportModuleResult,
  ReportModuleTable,
  ReportsModuleKey,
  ReportsQuery,
  ReportTableColumn,
  ReportTableRow,
  ReportValidationResult,
} from "@/types/report";
import type { ReportSupportedCurrency } from "@/lib/reports/constants";
import type { BookingRecord } from "@/types/booking";

const PK_AIRPORTS = new Set(["KHI", "LHE", "ISB", "PEW", "SKT", "UET", "MUX", "LYP", "GWD", "BHV"]);

const OPERATIONS_LIMITATIONS = [
  "Ticketing state is informational only. No live ticket issuance is available in this dashboard foundation.",
  "GDS ticketing may be blocked where authorized printer capability is unavailable.",
  "NDC fulfilment does not use GDS PNR printer assumptions.",
  "Cancellation eligibility does not execute cancellation.",
  "No issue, reissue, void, refund, exchange or cancellation mutations are available.",
];

function validationForQuery(query: ReportsQuery): ReportValidationResult {
  const custom = validateCustomDateRange(query.datePreset, query.startDate, query.endDate);
  if (!custom.valid) {
    return { valid: false, issues: [{ code: "invalid_date_range", message: custom.message ?? "Invalid date range", field: "startDate" }] };
  }
  return { valid: true, issues: [] };
}

function currencyFor(query: ReportsQuery): ReportSupportedCurrency {
  return query.currency === "all" ? "PKR" : query.currency;
}

function paginateTable(
  rows: ReportTableRow[],
  query: ReportsQuery,
  columns: ReportTableColumn[],
): ReportModuleTable {
  const total = rows.length;
  const pageCount = Math.max(1, Math.ceil(total / query.pageSize));
  const page = Math.min(query.page, pageCount);
  const start = (page - 1) * query.pageSize;
  return {
    columns,
    rows: rows.slice(start, start + query.pageSize),
    total,
    page,
    pageSize: query.pageSize,
    pageCount,
  };
}

function sortRows(rows: ReportTableRow[], sort: string, direction: "asc" | "desc"): ReportTableRow[] {
  const sorted = [...rows].sort((a, b) => {
    const av = a[sort];
    const bv = b[sort];
    if (typeof av === "number" && typeof bv === "number") return av - bv;
    return String(av ?? "").localeCompare(String(bv ?? ""));
  });
  return direction === "desc" ? sorted.reverse() : sorted;
}

function leadTimeBand(bookingDate: string, departureDate: string): string | null {
  const booked = new Date(`${bookingDate}T12:00:00Z`).getTime();
  const depart = new Date(`${departureDate}T12:00:00Z`).getTime();
  if (Number.isNaN(booked) || Number.isNaN(depart)) return null;
  const days = Math.round((depart - booked) / 86_400_000);
  if (days < 0) return null;
  if (days === 0) return "Same day";
  if (days <= 3) return "1–3 days";
  if (days <= 7) return "4–7 days";
  if (days <= 14) return "8–14 days";
  if (days <= 30) return "15–30 days";
  return "31+ days";
}

function isDomestic(origin: string, destination: string): boolean {
  return PK_AIRPORTS.has(origin) && PK_AIRPORTS.has(destination);
}

function buildExportManifest(
  module: ReportsModuleKey,
  query: ReportsQuery,
  dateRange: ReportDateRange,
  columns: { key: string; header: string }[],
  rowCount: number,
): ReportExportManifest {
  return {
    id: `JP-RPT-EXP-${module.toUpperCase()}`,
    reportKey: module,
    title: `${module.charAt(0).toUpperCase()}${module.slice(1)} report export`,
    generatedAt: REPORT_REFERENCE_DATE,
    dateRange,
    currency: query.currency,
    columns: columns.map((c) => ({ ...c, includeByDefault: true })),
    rowCount,
    previewOnly: true,
  };
}

function salesMetrics(bookings: BookingRecord[], payments: ReturnType<typeof filteredGraph>["payments"], currency: ReportSupportedCurrency): ReportMetric[] {
  const gross = sumSameCurrencyAmounts(bookings.map((b) => ({ amount: b.totalAmount, currency: b.currency })));
  const collected = sumSameCurrencyAmounts(
    payments.filter((p) => p.paymentStatus === "paid" || p.paymentStatus === "partial").map((p) => ({ amount: p.paidAmount, currency: p.currency })),
  );
  const outstanding = sumSameCurrencyAmounts(bookings.map((b) => ({ amount: Math.max(0, b.totalAmount - b.amountPaid), currency: b.currency })));
  const refunded = sumSameCurrencyAmounts(
    payments.filter((p) => p.paymentStatus === "refunded" || p.paymentStatus === "partially_refunded").map((p) => ({ amount: p.refundedAmount, currency: p.currency })),
  );
  const agentCount = bookings.filter((b) => bookingChannel(b) === "agent").length;
  const directCount = bookings.length - agentCount;
  const avg = bookings.length > 0 && gross.total !== null ? gross.total / bookings.length : null;
  const mk = (key: ReportMetric["key"], label: string, value: number | null, formatted: string, trend: ReportMetric["trend"] = "neutral"): ReportMetric => ({
    key,
    label,
    value,
    formattedValue: formatted,
    currency: gross.currency,
    trend,
    comparisonDelta: null,
    comparisonLabel: null,
    unavailableReason: value === null ? "Multiple currencies in selected range" : null,
  });
  return [
    mk("gross_booking_value", "Gross booking value", gross.mixed ? null : gross.total, gross.mixed ? "Unavailable" : formatCurrency(gross.total!, currency)),
    mk("collected_payments", "Collected revenue", collected.mixed ? null : collected.total, collected.mixed ? "Unavailable" : formatCurrency(collected.total!, currency), "positive"),
    mk("outstanding_balance", "Outstanding value", outstanding.mixed ? null : outstanding.total, outstanding.mixed ? "Unavailable" : formatCurrency(outstanding.total!, currency), "warning"),
    mk("refunded_amount", "Refunded value", refunded.mixed ? null : refunded.total, refunded.mixed ? "Unavailable" : formatCurrency(refunded.total!, currency)),
    mk("booking_count", "Booking count", bookings.length, String(bookings.length)),
    {
      key: "gross_booking_value",
      label: "Average booking value",
      value: avg,
      formattedValue: avg === null ? "Unavailable" : formatCurrency(avg, currency),
      currency,
      trend: "neutral",
      comparisonDelta: null,
      comparisonLabel: null,
      unavailableReason: avg === null ? "No bookings in range" : null,
    },
    mk("agent_assisted_booking_count", "Agent-assisted sales", agentCount, String(agentCount)),
    mk("direct_booking_count", "Direct sales", directCount, String(directCount)),
  ];
}

function bookingFunnel(bookings: BookingRecord[], pnrs: ReturnType<typeof filteredGraph>["pnrs"], tickets: ReturnType<typeof filteredGraph>["tickets"]): ReportFunnelStage[] {
  return [
    { id: "pending", label: "Pending bookings", count: bookings.filter((b) => b.bookingStatus === "pending").length, description: "Booking status: pending", statusType: "booking" },
    { id: "confirmed", label: "Confirmed bookings", count: bookings.filter((b) => b.bookingStatus === "confirmed").length, description: "Booking status: confirmed", statusType: "booking" },
    { id: "pnr", label: "PNR/order created", count: pnrs.length, description: "Operational PNR or NDC order records", statusType: "pnr" },
    { id: "paid", label: "Paid bookings", count: bookings.filter((b) => b.paymentStatus === "paid").length, description: "Payment status: paid", statusType: "payment" },
    { id: "ticketed", label: "Ticketed/fulfilled", count: tickets.filter((t) => t.issueStatus === "Issued").length, description: "Ticket/document issued", statusType: "ticket" },
    { id: "cancelled", label: "Cancelled/failed", count: bookings.filter((b) => b.bookingStatus === "cancelled" || b.bookingStatus === "failed").length, description: "Booking status: cancelled or failed", statusType: "booking" },
  ];
}

function comparisonMetrics(query: ReportsQuery, dateRange: ReportDateRange, comparison: ReturnType<typeof resolveComparisonPeriod> & { mode: ReportsQuery["comparison"] }): ReportMetric[] | null {
  if (comparison.mode === "none" || !comparison.startDate || !comparison.endDate) return null;
  const graph = getOperationalFixtureGraph();
  const compRange: ReportDateRange = { preset: "custom", startDate: comparison.startDate, endDate: comparison.endDate };
  const current = aggregateReportMetrics(graph, dateRange, query.currency);
  const previous = aggregateReportMetrics(graph, compRange, query.currency);
  return enrichMetricsWithComparison(current, previous, comparison.label);
}

export function buildReportModule(module: ReportsModuleKey, query: ReportsQuery): ReportModuleResult {
  const graph = getOperationalFixtureGraph();
  const validation = validationForQuery(query);
  const dateRange = resolveDatePreset(query.datePreset, query.startDate, query.endDate);
  const comparison = { mode: query.comparison, ...resolveComparisonPeriod(query.comparison, dateRange) };
  const facets = buildReportFacets(graph);
  const currency = currencyFor(query);
  const emptyTable: ReportModuleTable = { columns: [], rows: [], total: 0, page: 1, pageSize: query.pageSize, pageCount: 1 };

  if (!validation.valid) {
    return {
      state: "ready",
      module,
      query,
      dateRange,
      comparison,
      validation,
      referenceDate: REPORT_REFERENCE_DATE,
      metrics: [],
      facets,
      series: {},
      breakdowns: {},
      charts: {},
      attentionQueue: [],
      funnel: [],
      table: emptyTable,
      exportRows: [],
      exportManifest: buildExportManifest(module, query, dateRange, [], 0),
      limitationNotices: module === "operations" ? OPERATIONS_LIMITATIONS : [],
    };
  }

  const filtered = filteredGraph(graph, dateRange, query);
  const { bookings, payments, pnrs, tickets } = filtered;
  const compMetrics = comparisonMetrics(query, dateRange, comparison);
  let metrics = compMetrics ?? aggregateReportMetrics(graph, dateRange, query.currency);

  const bookingValueSeries = buildTimeSeries(
    "booking_value",
    "Booking value trend",
    dateRange.startDate,
    dateRange.endDate,
    query.granularity,
    bookings.map((b) => ({ date: b.bookingDate, value: b.totalAmount })),
    currency,
  );
  const collectionSeries = buildTimeSeries(
    "payment_collection",
    "Payment collection trend",
    dateRange.startDate,
    dateRange.endDate,
    query.granularity,
    payments.filter((p) => p.paymentStatus === "paid" || p.paymentStatus === "partial").map((p) => ({ date: p.transactionDate, value: p.paidAmount })),
    currency,
  );

  const statusBreakdown = buildBreakdownRows(
    countBreakdown(bookings.map((b) => b.bookingStatus), (s) => s.charAt(0).toUpperCase() + s.slice(1)),
    null,
  );
  const channelBreakdown = buildBreakdownRows(
    countBreakdown(bookings.map((b) => (bookingChannel(b) === "agent" ? "Agent-assisted" : "Direct"))),
    null,
  );
  const routeBreakdown = buildBreakdownRows(
    countBreakdown(bookings.map((b) => bookingRoute(b))),
    currency,
  );
  const supplierBreakdown = buildBreakdownRows(countBreakdown(bookings.map((b) => b.supplier)), currency);
  const airlineBreakdown = buildBreakdownRows(countBreakdown(bookings.map((b) => b.airline)), currency);
  const agentBreakdown = buildBreakdownRows(
    countBreakdown(bookings.map((b) => bookingAgentName(b) || "Direct")),
    currency,
  );
  const tripBreakdown = buildBreakdownRows(
    countBreakdown(bookings.map((b) => tripTypeLabel(b.tripType))),
    null,
  );
  const cabinBreakdown = buildBreakdownRows(
    countBreakdown(pnrs.map((p) => p.cabin)),
    null,
  );
  const leadTimeBreakdown = buildBreakdownRows(
    countBreakdown(
      bookings
        .map((b) => leadTimeBand(b.bookingDate, b.departureDate))
        .filter((x): x is string => Boolean(x)),
    ),
    null,
  );
  const domesticBreakdown = buildBreakdownRows(
    countBreakdown(bookings.map((b) => (isDomestic(b.origin, b.destination) ? "Domestic" : "International"))),
    null,
  );
  const paymentStatusBreakdown = buildBreakdownRows(
    countBreakdown(payments.map((p) => p.paymentStatus.replace(/_/g, " "))),
    null,
  );
  const paymentMethodBreakdown = buildBreakdownRows(countBreakdown(payments.map((p) => p.paymentMethod.replace(/_/g, " "))), null);
  const pnrChannelBreakdown = buildBreakdownRows(countBreakdown(pnrs.map((p) => p.channel)), null);
  const fulfilmentBreakdown = buildBreakdownRows(countBreakdown(pnrs.map((p) => p.fulfilmentStatus)), null);
  const ticketingBreakdown = buildBreakdownRows(countBreakdown(pnrs.map((p) => p.ticketingStatus)), null);
  const lifecycleBreakdown = buildBreakdownRows(countBreakdown(pnrs.map((p) => p.lifecycleStatus)), null);
  const cancellationBreakdown = buildBreakdownRows(countBreakdown(pnrs.map((p) => p.cancellationEligibility)), null);

  const breakdowns: ReportModuleResult["breakdowns"] = {
    booking_status: statusBreakdown,
    channel: channelBreakdown,
    route: routeBreakdown,
    supplier: supplierBreakdown,
    airline: airlineBreakdown,
    agent: agentBreakdown,
    trip_type: tripBreakdown,
    cabin: cabinBreakdown,
    lead_time: leadTimeBreakdown,
    domestic_international: domesticBreakdown,
    payment_status: paymentStatusBreakdown,
    payment_method: paymentMethodBreakdown,
    pnr_channel: pnrChannelBreakdown,
    fulfilment: fulfilmentBreakdown,
    ticketing: ticketingBreakdown,
    lifecycle: lifecycleBreakdown,
    cancellation: cancellationBreakdown,
  };

  const charts: ReportModuleResult["charts"] = {
    booking_status: breakdownToChartSegments(statusBreakdown),
    channel: breakdownToChartSegments(channelBreakdown),
    route: breakdownToChartSegments(routeBreakdown.slice(0, 6)),
    supplier: breakdownToChartSegments(supplierBreakdown),
    fulfilment: breakdownToChartSegments(fulfilmentBreakdown),
    pnr_channel: breakdownToChartSegments(pnrChannelBreakdown),
    payment_status: breakdownToChartSegments(paymentStatusBreakdown),
    payment_method: breakdownToChartSegments(paymentMethodBreakdown),
    ticketing: breakdownToChartSegments(ticketingBreakdown),
    airline: breakdownToChartSegments(airlineBreakdown),
  };

  const attentionQueue = buildAttentionQueue(graph, query);
  const funnel = bookingFunnel(bookings, pnrs, tickets);

  let tableColumns: ReportTableColumn[] = [];
  let tableRows: ReportTableRow[] = [];

  if (module === "sales") {
    metrics = salesMetrics(bookings, payments, currency);
    tableColumns = [
      { key: "label", label: "Route", sortable: true },
      { key: "bookings", label: "Bookings", align: "end", sortable: true },
      { key: "gross", label: "Gross value", align: "end", sortable: true },
      { key: "collected", label: "Collected", align: "end", sortable: true },
      { key: "share", label: "Share", align: "end", sortable: true },
    ];
    tableRows = routeBreakdown.map((row) => {
      const routeBookings = bookings.filter((b) => bookingRoute(b) === row.label);
      const gross = sumSameCurrencyAmounts(routeBookings.map((b) => ({ amount: b.totalAmount, currency: b.currency })));
      const collected = sumSameCurrencyAmounts(
        payments
          .filter((p) => routeBookings.some((b) => b.id === p.bookingId))
          .filter((p) => p.paymentStatus === "paid" || p.paymentStatus === "partial")
          .map((p) => ({ amount: p.paidAmount, currency: p.currency })),
      );
      return {
        label: row.label,
        bookings: routeBookings.length,
        gross: gross.total !== null ? formatCurrency(gross.total, currency) : "Unavailable",
        collected: collected.total !== null ? formatCurrency(collected.total, currency) : "Unavailable",
        share: row.sharePercent !== null ? `${row.sharePercent}%` : "—",
        href: `/bookings?route=${encodeURIComponent(row.label)}`,
      };
    });
  } else if (module === "bookings") {
    metrics = [
      { key: "booking_count", label: "Total bookings", value: bookings.length, formattedValue: String(bookings.length), currency: null, trend: "neutral", comparisonDelta: null, comparisonLabel: null, unavailableReason: null },
      { key: "booking_count", label: "Confirmed bookings", value: bookings.filter((b) => b.bookingStatus === "confirmed").length, formattedValue: String(bookings.filter((b) => b.bookingStatus === "confirmed").length), currency: null, trend: "positive", comparisonDelta: null, comparisonLabel: null, unavailableReason: null },
      { key: "booking_count", label: "Pending bookings", value: bookings.filter((b) => b.bookingStatus === "pending").length, formattedValue: String(bookings.filter((b) => b.bookingStatus === "pending").length), currency: null, trend: "warning", comparisonDelta: null, comparisonLabel: null, unavailableReason: null },
      { key: "booking_count", label: "Cancelled bookings", value: bookings.filter((b) => b.bookingStatus === "cancelled").length, formattedValue: String(bookings.filter((b) => b.bookingStatus === "cancelled").length), currency: null, trend: "neutral", comparisonDelta: null, comparisonLabel: null, unavailableReason: null },
      { key: "booking_count", label: "Failed/review bookings", value: bookings.filter((b) => b.bookingStatus === "failed").length, formattedValue: String(bookings.filter((b) => b.bookingStatus === "failed").length), currency: null, trend: "warning", comparisonDelta: null, comparisonLabel: null, unavailableReason: null },
      { key: "agent_assisted_booking_count", label: "Agent-assisted share", value: bookings.filter((b) => bookingChannel(b) === "agent").length, formattedValue: `${bookings.length ? Math.round((bookings.filter((b) => bookingChannel(b) === "agent").length / bookings.length) * 100) : 0}%`, currency: null, trend: "neutral", comparisonDelta: null, comparisonLabel: null, unavailableReason: null },
      { key: "direct_booking_count", label: "Direct share", value: bookings.filter((b) => bookingChannel(b) !== "agent").length, formattedValue: `${bookings.length ? Math.round((bookings.filter((b) => bookingChannel(b) !== "agent").length / bookings.length) * 100) : 0}%`, currency: null, trend: "neutral", comparisonDelta: null, comparisonLabel: null, unavailableReason: null },
    ];
    tableColumns = [
      { key: "id", label: "Booking", sortable: true },
      { key: "bookingDate", label: "Created", sortable: true },
      { key: "customer", label: "Customer", sortable: true },
      { key: "channel", label: "Channel", sortable: true },
      { key: "route", label: "Route", sortable: true },
      { key: "bookingStatus", label: "Booking status", sortable: true },
      { key: "paymentStatus", label: "Payment status", sortable: true },
      { key: "gross", label: "Gross value", align: "end", sortable: true },
    ];
    tableRows = bookings.map((b) => ({
      id: b.id,
      bookingDate: formatDate(b.bookingDate),
      customer: b.customerName,
      channel: bookingChannel(b) === "agent" ? bookingAgentName(b) || "Agent" : "Direct",
      route: bookingRoute(b),
      bookingStatus: b.bookingStatus,
      paymentStatus: b.paymentStatus,
      gross: formatCurrency(b.totalAmount, b.currency as ReportSupportedCurrency),
      href: `/bookings?selectedId=${encodeURIComponent(b.id)}`,
    }));
  } else if (module === "payments") {
    const gross = sumSameCurrencyAmounts(bookings.map((b) => ({ amount: b.totalAmount, currency: b.currency })));
    const collected = sumSameCurrencyAmounts(
      payments.filter((p) => p.paymentStatus === "paid" || p.paymentStatus === "partial").map((p) => ({ amount: p.paidAmount, currency: p.currency })),
    );
    const outstanding = sumSameCurrencyAmounts(bookings.map((b) => ({ amount: Math.max(0, b.totalAmount - b.amountPaid), currency: b.currency })));
    const refunded = sumSameCurrencyAmounts(
      payments.filter((p) => p.paymentStatus === "refunded" || p.paymentStatus === "partially_refunded").map((p) => ({ amount: p.refundedAmount, currency: p.currency })),
    );
    const collectionRate = gross.total && gross.total > 0 && collected.total !== null ? (collected.total / gross.total) * 100 : null;
    metrics = [
      { key: "gross_booking_value", label: "Gross booking value", value: gross.mixed ? null : gross.total, formattedValue: gross.mixed ? "Unavailable" : formatCurrency(gross.total!, currency), currency, trend: "neutral", comparisonDelta: null, comparisonLabel: null, unavailableReason: gross.mixed ? "Multiple currencies" : null },
      { key: "collected_payments", label: "Collected amount", value: collected.mixed ? null : collected.total, formattedValue: collected.mixed ? "Unavailable" : formatCurrency(collected.total!, currency), currency, trend: "positive", comparisonDelta: null, comparisonLabel: null, unavailableReason: null },
      { key: "outstanding_balance", label: "Outstanding amount", value: outstanding.mixed ? null : outstanding.total, formattedValue: outstanding.mixed ? "Unavailable" : formatCurrency(outstanding.total!, currency), currency, trend: "warning", comparisonDelta: null, comparisonLabel: null, unavailableReason: null },
      { key: "refunded_amount", label: "Refunded amount", value: refunded.mixed ? null : refunded.total, formattedValue: refunded.mixed ? "Unavailable" : formatCurrency(refunded.total!, currency), currency, trend: "neutral", comparisonDelta: null, comparisonLabel: null, unavailableReason: null },
      { key: "collected_payments", label: "Collection rate", value: collectionRate, formattedValue: collectionRate === null ? "Unavailable" : `${Math.round(collectionRate * 10) / 10}%`, currency: null, trend: "positive", comparisonDelta: null, comparisonLabel: null, unavailableReason: collectionRate === null ? "Invalid denominator" : null },
      { key: "booking_count", label: "Payment count", value: payments.length, formattedValue: String(payments.length), currency: null, trend: "neutral", comparisonDelta: null, comparisonLabel: null, unavailableReason: null },
      { key: "review_required_count", label: "Reconciliation required", value: payments.filter((p) => p.reconciliationStatus === "unreconciled").length, formattedValue: String(payments.filter((p) => p.reconciliationStatus === "unreconciled").length), currency: null, trend: "warning", comparisonDelta: null, comparisonLabel: null, unavailableReason: null },
    ];
    tableColumns = [
      { key: "transactionId", label: "Transaction", sortable: true },
      { key: "bookingId", label: "Booking", sortable: true },
      { key: "customer", label: "Customer", sortable: true },
      { key: "method", label: "Method", sortable: true },
      { key: "status", label: "Status", sortable: true },
      { key: "amount", label: "Amount", align: "end", sortable: true },
      { key: "reconciliation", label: "Reconciliation", sortable: true },
    ];
    tableRows = payments.map((p) => ({
      transactionId: p.transactionId,
      bookingId: p.bookingId,
      customer: p.customerName,
      method: p.paymentMethod.replace(/_/g, " "),
      status: p.paymentStatus.replace(/_/g, " "),
      amount: formatCurrency(p.paidAmount, p.currency as ReportSupportedCurrency),
      reconciliation: p.reconciliationStatus.replace(/_/g, " "),
      href: `/payments?selectedId=${encodeURIComponent(p.transactionId)}`,
    }));
  } else if (module === "operations") {
    metrics = [
      { key: "pnr_order_count", label: "PNR/order volume", value: pnrs.length, formattedValue: String(pnrs.length), currency: null, trend: "neutral", comparisonDelta: null, comparisonLabel: null, unavailableReason: null },
      { key: "gds_share", label: "GDS PNR count", value: pnrs.filter((p) => p.referenceType === "GDS PNR").length, formattedValue: String(pnrs.filter((p) => p.referenceType === "GDS PNR").length), currency: null, trend: "neutral", comparisonDelta: null, comparisonLabel: null, unavailableReason: null },
      { key: "ndc_share", label: "NDC order count", value: pnrs.filter((p) => p.referenceType === "NDC Order").length, formattedValue: String(pnrs.filter((p) => p.referenceType === "NDC Order").length), currency: null, trend: "neutral", comparisonDelta: null, comparisonLabel: null, unavailableReason: null },
      { key: "pnr_order_count", label: "One API count", value: pnrs.filter((p) => p.channel === "One API").length, formattedValue: String(pnrs.filter((p) => p.channel === "One API").length), currency: null, trend: "neutral", comparisonDelta: null, comparisonLabel: null, unavailableReason: null },
      { key: "issued_ticket_count", label: "Issued tickets/documents", value: tickets.filter((t) => t.issueStatus === "Issued").length, formattedValue: String(tickets.filter((t) => t.issueStatus === "Issued").length), currency: null, trend: "positive", comparisonDelta: null, comparisonLabel: null, unavailableReason: null },
      { key: "pending_fulfilment_count", label: "Pending fulfilment", value: pnrs.filter((p) => p.fulfilmentStatus === "Pending" || p.fulfilmentStatus === "Partially Fulfilled").length, formattedValue: String(pnrs.filter((p) => p.fulfilmentStatus === "Pending" || p.fulfilmentStatus === "Partially Fulfilled").length), currency: null, trend: "warning", comparisonDelta: null, comparisonLabel: null, unavailableReason: null },
      { key: "review_required_count", label: "Ticketing blocked", value: pnrs.filter((p) => p.ticketingStatus === "Ticketing Blocked").length, formattedValue: String(pnrs.filter((p) => p.ticketingStatus === "Ticketing Blocked").length), currency: null, trend: "warning", comparisonDelta: null, comparisonLabel: null, unavailableReason: null },
      { key: "cancellation_eligible_count", label: "Cancellation eligible", value: pnrs.filter((p) => p.cancellationEligibility === "Eligible").length, formattedValue: String(pnrs.filter((p) => p.cancellationEligibility === "Eligible").length), currency: null, trend: "neutral", comparisonDelta: null, comparisonLabel: null, unavailableReason: null },
    ];
    tableColumns = [
      { key: "reference", label: "PNR/order", sortable: true },
      { key: "channel", label: "Channel", sortable: true },
      { key: "lifecycle", label: "Lifecycle", sortable: true },
      { key: "fulfilment", label: "Fulfilment", sortable: true },
      { key: "ticketing", label: "Ticketing", sortable: true },
      { key: "bookingId", label: "Booking", sortable: true },
    ];
    tableRows = pnrs.map((p) => ({
      reference: p.externalReference,
      channel: p.channel,
      lifecycle: p.lifecycleStatus,
      fulfilment: p.fulfilmentStatus,
      ticketing: p.ticketingStatus,
      bookingId: p.bookingId,
      href: `/pnrs?selectedId=${encodeURIComponent(p.id)}`,
    }));
  }

  const sortedRows = sortRows(tableRows, query.sort, query.direction);
  const table = paginateTable(sortedRows, query, tableColumns);
  const exportColumns = tableColumns.map((c) => ({ key: c.key, header: c.label }));
  const exportManifest = buildExportManifest(module, query, dateRange, exportColumns, sortedRows.length);

  if (module === "overview") {
    metrics = compMetrics ?? aggregateReportMetrics(graph, dateRange, query.currency);
  }

  return {
    state: "ready",
    module,
    query,
    dateRange,
    comparison,
    validation,
    referenceDate: REPORT_REFERENCE_DATE,
    metrics,
    facets,
    series: { booking_value: bookingValueSeries, payment_collection: collectionSeries },
    breakdowns,
    charts,
    attentionQueue,
    funnel,
    table,
    exportRows: sortedRows,
    exportManifest,
    limitationNotices: module === "operations" ? OPERATIONS_LIMITATIONS : [],
  };
}

export function buildReportCsv(result: ReportModuleResult): string {
  const headers = result.exportManifest.columns.map((c) => c.header);
  const keys = result.exportManifest.columns.map((c) => c.key);
  const allRows = sortRows(
    result.table.rows.length === result.table.total
      ? result.table.rows
      : Array.from({ length: result.table.total }, (_, i) => result.table.rows[i] ?? {}),
    result.query.sort,
    result.query.direction,
  );
  const rows = keys.length
    ? (result.table.total <= result.table.rows.length ? result.table.rows : allRows).map((row) => keys.map((k) => row[k] ?? ""))
    : [];
  return buildCsvContent(headers, rows);
}
