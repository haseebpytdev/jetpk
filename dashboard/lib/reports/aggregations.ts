import { mockBookings } from "@/mocks/booking-fixtures";
import { mockCustomers } from "@/mocks/customer-fixtures";
import { mockTransactions } from "@/mocks/payment-fixtures";
import { mockPnrs } from "@/mocks/pnr-fixtures";
import { mockTickets } from "@/mocks/ticket-fixtures";
import { filterByCurrency, sumSameCurrencyAmounts } from "@/lib/reports/currency";
import { formatCurrency } from "@/lib/format";
import type { ReportDateRange, ReportMetric, ReportMetricKey } from "@/types/report";
import type { ReportSupportedCurrency } from "@/lib/reports/constants";
import type { BookingRecord } from "@/types/booking";
import type { TransactionRecord } from "@/types/payment";
import type { PnrRecord } from "@/types/pnr";
import type { TicketRecord } from "@/types/ticket";

export type OperationalFixtureGraph = {
  bookings: BookingRecord[];
  payments: TransactionRecord[];
  customers: typeof mockCustomers;
  pnrs: PnrRecord[];
  tickets: TicketRecord[];
};

export function getOperationalFixtureGraph(): OperationalFixtureGraph {
  return {
    bookings: mockBookings,
    payments: mockTransactions,
    customers: mockCustomers,
    pnrs: mockPnrs,
    tickets: mockTickets,
  };
}

function inDateRange(isoDate: string, range: ReportDateRange): boolean {
  return isoDate >= range.startDate && isoDate <= range.endDate;
}

function filterBookings(
  graph: OperationalFixtureGraph,
  range: ReportDateRange,
  currency: ReportSupportedCurrency | "all",
): BookingRecord[] {
  const inRange = graph.bookings.filter((b) => inDateRange(b.bookingDate, range));
  return filterByCurrency(inRange, currency);
}

function filterPayments(
  graph: OperationalFixtureGraph,
  range: ReportDateRange,
  currency: ReportSupportedCurrency | "all",
): TransactionRecord[] {
  const inRange = graph.payments.filter((p) => inDateRange(p.transactionDate, range));
  return filterByCurrency(inRange, currency);
}

function isAgentAssisted(source: string): boolean {
  return source.toLowerCase().includes("agent");
}

function formatMetricValue(
  key: ReportMetricKey,
  value: number | null,
  currency: ReportSupportedCurrency | null,
): string {
  if (value === null) {
    return "Unavailable";
  }
  if (
    key === "gross_booking_value" ||
    key === "collected_payments" ||
    key === "outstanding_balance" ||
    key === "refunded_amount" ||
    key === "supplier_exposure"
  ) {
    return currency ? formatCurrency(value, currency) : String(value);
  }
  if (key === "gds_share" || key === "ndc_share") {
    return `${value.toFixed(1)}%`;
  }
  return String(value);
}

function unavailableMetric(key: ReportMetricKey, label: string, reason: string): ReportMetric {
  return {
    key,
    label,
    value: null,
    formattedValue: "Unavailable",
    currency: null,
    trend: "unavailable",
    comparisonDelta: null,
    comparisonLabel: null,
    unavailableReason: reason,
  };
}

export function aggregateReportMetrics(
  graph: OperationalFixtureGraph,
  range: ReportDateRange,
  currency: ReportSupportedCurrency | "all",
): ReportMetric[] {
  const bookings = filterBookings(graph, range, currency);
  const payments = filterPayments(graph, range, currency);
  const pnrs = graph.pnrs.filter((p) => inDateRange(p.createdDate, range));
  const tickets = graph.tickets.filter((t) => t.issueDate && inDateRange(t.issueDate, range));

  const gross = sumSameCurrencyAmounts(
    bookings.map((b) => ({ amount: b.totalAmount, currency: b.currency })),
  );
  const collected = sumSameCurrencyAmounts(
    payments
      .filter((p) => p.paymentStatus === "paid" || p.paymentStatus === "partial")
      .map((p) => ({ amount: p.paidAmount, currency: p.currency })),
  );
  const outstanding = sumSameCurrencyAmounts(
    bookings.map((b) => ({
      amount: Math.max(0, b.totalAmount - b.amountPaid),
      currency: b.currency,
    })),
  );
  const refunded = sumSameCurrencyAmounts(
    payments
      .filter((p) => p.paymentStatus === "refunded" || p.paymentStatus === "partially_refunded")
      .map((p) => ({ amount: p.refundedAmount, currency: p.currency })),
  );

  const gdsCount = pnrs.filter((p) => p.referenceType === "GDS PNR").length;
  const ndcCount = pnrs.filter((p) => p.referenceType === "NDC Order").length;
  const pnrTotal = pnrs.length;
  const gdsShare = pnrTotal > 0 ? (gdsCount / pnrTotal) * 100 : null;
  const ndcShare = pnrTotal > 0 ? (ndcCount / pnrTotal) * 100 : null;

  const customerIds = new Set(bookings.map((b) => b.customerEmail));

  const metrics: ReportMetric[] = [
    {
      key: "gross_booking_value",
      label: "Gross booking value",
      value: gross.mixed ? null : gross.total,
      formattedValue: formatMetricValue("gross_booking_value", gross.mixed ? null : gross.total, gross.currency),
      currency: gross.currency,
      trend: "neutral",
      comparisonDelta: null,
      comparisonLabel: null,
      unavailableReason: gross.mixed ? "Multiple currencies in selected range" : null,
    },
    {
      key: "collected_payments",
      label: "Collected payments",
      value: collected.mixed ? null : collected.total,
      formattedValue: formatMetricValue("collected_payments", collected.mixed ? null : collected.total, collected.currency),
      currency: collected.currency,
      trend: "positive",
      comparisonDelta: null,
      comparisonLabel: null,
      unavailableReason: collected.mixed ? "Multiple currencies in selected range" : null,
    },
    {
      key: "outstanding_balance",
      label: "Outstanding balance",
      value: outstanding.mixed ? null : outstanding.total,
      formattedValue: formatMetricValue("outstanding_balance", outstanding.mixed ? null : outstanding.total, outstanding.currency),
      currency: outstanding.currency,
      trend: outstanding.total && outstanding.total > 0 ? "warning" : "neutral",
      comparisonDelta: null,
      comparisonLabel: null,
      unavailableReason: outstanding.mixed ? "Multiple currencies in selected range" : null,
    },
    {
      key: "refunded_amount",
      label: "Refunded amount",
      value: refunded.mixed ? null : refunded.total,
      formattedValue: formatMetricValue("refunded_amount", refunded.mixed ? null : refunded.total, refunded.currency),
      currency: refunded.currency,
      trend: "neutral",
      comparisonDelta: null,
      comparisonLabel: null,
      unavailableReason: refunded.mixed ? "Multiple currencies in selected range" : null,
    },
    {
      key: "booking_count",
      label: "Booking count",
      value: bookings.length,
      formattedValue: formatMetricValue("booking_count", bookings.length, null),
      currency: null,
      trend: "neutral",
      comparisonDelta: null,
      comparisonLabel: null,
      unavailableReason: null,
    },
    {
      key: "customer_count",
      label: "Customer count",
      value: customerIds.size,
      formattedValue: formatMetricValue("customer_count", customerIds.size, null),
      currency: null,
      trend: "neutral",
      comparisonDelta: null,
      comparisonLabel: null,
      unavailableReason: null,
    },
    {
      key: "agent_assisted_booking_count",
      label: "Agent-assisted bookings",
      value: bookings.filter((b) => isAgentAssisted(b.agentOrSource)).length,
      formattedValue: formatMetricValue(
        "agent_assisted_booking_count",
        bookings.filter((b) => isAgentAssisted(b.agentOrSource)).length,
        null,
      ),
      currency: null,
      trend: "neutral",
      comparisonDelta: null,
      comparisonLabel: null,
      unavailableReason: null,
    },
    {
      key: "direct_booking_count",
      label: "Direct bookings",
      value: bookings.filter((b) => !isAgentAssisted(b.agentOrSource)).length,
      formattedValue: formatMetricValue(
        "direct_booking_count",
        bookings.filter((b) => !isAgentAssisted(b.agentOrSource)).length,
        null,
      ),
      currency: null,
      trend: "neutral",
      comparisonDelta: null,
      comparisonLabel: null,
      unavailableReason: null,
    },
    {
      key: "supplier_exposure",
      label: "Supplier exposure",
      value: gross.mixed ? null : gross.total,
      formattedValue: formatMetricValue("supplier_exposure", gross.mixed ? null : gross.total, gross.currency),
      currency: gross.currency,
      trend: "neutral",
      comparisonDelta: null,
      comparisonLabel: null,
      unavailableReason: gross.mixed ? "Multiple currencies in selected range" : null,
    },
    {
      key: "issued_ticket_count",
      label: "Issued tickets",
      value: tickets.filter((t) => t.issueStatus === "Issued").length,
      formattedValue: formatMetricValue(
        "issued_ticket_count",
        tickets.filter((t) => t.issueStatus === "Issued").length,
        null,
      ),
      currency: null,
      trend: "positive",
      comparisonDelta: null,
      comparisonLabel: null,
      unavailableReason: null,
    },
    {
      key: "pending_fulfilment_count",
      label: "Pending fulfilment",
      value: pnrs.filter((p) => p.fulfilmentStatus === "Pending" || p.fulfilmentStatus === "Partially Fulfilled").length,
      formattedValue: formatMetricValue(
        "pending_fulfilment_count",
        pnrs.filter((p) => p.fulfilmentStatus === "Pending" || p.fulfilmentStatus === "Partially Fulfilled").length,
        null,
      ),
      currency: null,
      trend: "warning",
      comparisonDelta: null,
      comparisonLabel: null,
      unavailableReason: null,
    },
    {
      key: "pnr_order_count",
      label: "PNR / order count",
      value: pnrTotal,
      formattedValue: formatMetricValue("pnr_order_count", pnrTotal, null),
      currency: null,
      trend: "neutral",
      comparisonDelta: null,
      comparisonLabel: null,
      unavailableReason: null,
    },
    {
      key: "gds_share",
      label: "GDS share",
      value: gdsShare,
      formattedValue: formatMetricValue("gds_share", gdsShare, null),
      currency: null,
      trend: "neutral",
      comparisonDelta: null,
      comparisonLabel: null,
      unavailableReason: pnrTotal === 0 ? "No PNR records in range" : null,
    },
    {
      key: "ndc_share",
      label: "NDC share",
      value: ndcShare,
      formattedValue: formatMetricValue("ndc_share", ndcShare, null),
      currency: null,
      trend: "neutral",
      comparisonDelta: null,
      comparisonLabel: null,
      unavailableReason: pnrTotal === 0 ? "No PNR records in range" : null,
    },
    {
      key: "cancellation_eligible_count",
      label: "Cancellation-eligible",
      value: pnrs.filter((p) => p.cancellationEligibility === "Eligible").length,
      formattedValue: formatMetricValue(
        "cancellation_eligible_count",
        pnrs.filter((p) => p.cancellationEligibility === "Eligible").length,
        null,
      ),
      currency: null,
      trend: "neutral",
      comparisonDelta: null,
      comparisonLabel: null,
      unavailableReason: null,
    },
    {
      key: "review_required_count",
      label: "Review required",
      value: pnrs.filter((p) => p.lifecycleStatus === "Review Required").length,
      formattedValue: formatMetricValue(
        "review_required_count",
        pnrs.filter((p) => p.lifecycleStatus === "Review Required").length,
        null,
      ),
      currency: null,
      trend: "warning",
      comparisonDelta: null,
      comparisonLabel: null,
      unavailableReason: null,
    },
  ];

  if (currency === "all" && bookings.some((b) => b.currency !== bookings[0]?.currency)) {
    return metrics.map((m) =>
      m.currency !== null
        ? unavailableMetric(m.key, m.label, "Select a single currency to view monetary KPIs")
        : m,
    );
  }

  return metrics;
}

export function validateFixtureGraphIntegrity(graph: OperationalFixtureGraph): string[] {
  const issues: string[] = [];
  const bookingIds = new Set(graph.bookings.map((b) => b.id));

  for (const payment of graph.payments) {
    if (!bookingIds.has(payment.bookingId)) {
      issues.push(`Payment ${payment.paymentId} references missing booking ${payment.bookingId}`);
    }
  }

  for (const pnr of graph.pnrs) {
    if (!bookingIds.has(pnr.bookingId)) {
      issues.push(`PNR ${pnr.id} references missing booking ${pnr.bookingId}`);
    }
  }

  return issues;
}
