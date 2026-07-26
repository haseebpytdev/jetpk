import { REPORT_REFERENCE_DATE } from "@/lib/reports/constants";
import type { ReportAttentionItem } from "@/types/report";
import type { OperationalFixtureGraph } from "@/lib/reports/aggregations";
import { filteredGraph } from "@/lib/reports/query-filters";
import type { ReportsQuery } from "@/types/report";
import { resolveDatePreset } from "@/lib/reports/date-presets";

const CATEGORY_LABELS: Record<ReportAttentionItem["category"], string> = {
  outstanding_balance: "Outstanding balance",
  payment_reconciliation: "Payment reconciliation",
  pending_fulfilment: "Pending fulfilment",
  ticketing_blocked: "Ticketing blocked",
  supplier_response_pending: "Supplier response pending",
  review_required: "Review required",
  cancellation_eligible: "Cancellation eligible",
  deadline_expiring: "Deadline expiring",
};

function refDate(): string {
  return REPORT_REFERENCE_DATE.slice(0, 10);
}

export function buildAttentionQueue(
  graph: OperationalFixtureGraph,
  query: ReportsQuery,
): ReportAttentionItem[] {
  const range = resolveDatePreset(query.datePreset, query.startDate, query.endDate);
  const { bookings, payments, pnrs } = filteredGraph(graph, range, query);
  const items: ReportAttentionItem[] = [];
  const ref = refDate();

  for (const b of bookings.filter((x) => x.totalAmount - x.amountPaid > 0).slice(0, 5)) {
    items.push({
      id: `att-out-${b.id}`,
      category: "outstanding_balance",
      categoryLabel: CATEGORY_LABELS.outstanding_balance,
      title: b.id,
      description: `Outstanding balance on ${b.origin}–${b.destination} booking.`,
      href: `/bookings?selectedId=${encodeURIComponent(b.id)}`,
      linkLabel: "View booking",
    });
  }

  for (const p of payments.filter((x) => x.reconciliationStatus === "unreconciled").slice(0, 5)) {
    items.push({
      id: `att-rec-${p.transactionId}`,
      category: "payment_reconciliation",
      categoryLabel: CATEGORY_LABELS.payment_reconciliation,
      title: p.transactionId,
      description: `Reconciliation required for booking ${p.bookingId}.`,
      href: `/payments?selectedId=${encodeURIComponent(p.transactionId)}`,
      linkLabel: "View payment",
    });
  }

  for (const pnr of pnrs.filter((x) => x.fulfilmentStatus === "Pending" || x.fulfilmentStatus === "Partially Fulfilled").slice(0, 5)) {
    items.push({
      id: `att-ful-${pnr.id}`,
      category: "pending_fulfilment",
      categoryLabel: CATEGORY_LABELS.pending_fulfilment,
      title: pnr.externalReference,
      description: `${pnr.channel} — ${pnr.fulfilmentStatus}.`,
      href: `/pnrs?selectedId=${encodeURIComponent(pnr.id)}`,
      linkLabel: "View PNR/order",
    });
  }

  for (const pnr of pnrs.filter((x) => x.ticketingStatus === "Ticketing Blocked").slice(0, 5)) {
    items.push({
      id: `att-tkt-${pnr.id}`,
      category: "ticketing_blocked",
      categoryLabel: CATEGORY_LABELS.ticketing_blocked,
      title: pnr.externalReference,
      description: "Ticketing blocked — printer authorization or fulfilment path unavailable.",
      href: `/pnrs?selectedId=${encodeURIComponent(pnr.id)}`,
      linkLabel: "View PNR/order",
    });
  }

  for (const pnr of pnrs.filter((x) => x.lifecycleStatus === "Pending Supplier").slice(0, 3)) {
    items.push({
      id: `att-sup-${pnr.id}`,
      category: "supplier_response_pending",
      categoryLabel: CATEGORY_LABELS.supplier_response_pending,
      title: pnr.externalReference,
      description: `Awaiting supplier response via ${pnr.channel}.`,
      href: `/pnrs?selectedId=${encodeURIComponent(pnr.id)}`,
      linkLabel: "View PNR/order",
    });
  }

  for (const pnr of pnrs.filter((x) => x.lifecycleStatus === "Review Required").slice(0, 3)) {
    items.push({
      id: `att-rev-${pnr.id}`,
      category: "review_required",
      categoryLabel: CATEGORY_LABELS.review_required,
      title: pnr.externalReference,
      description: "Manual review required before fulfilment continues.",
      href: `/pnrs?selectedId=${encodeURIComponent(pnr.id)}`,
      linkLabel: "Review eligibility",
    });
  }

  for (const pnr of pnrs.filter((x) => x.cancellationEligibility === "Eligible").slice(0, 3)) {
    items.push({
      id: `att-can-${pnr.id}`,
      category: "cancellation_eligible",
      categoryLabel: CATEGORY_LABELS.cancellation_eligible,
      title: pnr.externalReference,
      description: "Cancellation eligibility is informational only — no live cancellation in preview.",
      href: `/pnrs?selectedId=${encodeURIComponent(pnr.id)}`,
      linkLabel: "Review eligibility",
    });
  }

  for (const pnr of pnrs.filter((x) => x.ticketingDeadline && x.ticketingDeadline <= ref).slice(0, 3)) {
    items.push({
      id: `att-dead-${pnr.id}`,
      category: "deadline_expiring",
      categoryLabel: CATEGORY_LABELS.deadline_expiring,
      title: pnr.externalReference,
      description: `Ticketing deadline on or before ${pnr.ticketingDeadline}.`,
      href: `/pnrs?selectedId=${encodeURIComponent(pnr.id)}`,
      linkLabel: "View PNR/order",
    });
  }

  return items.sort((a, b) => a.category.localeCompare(b.category) || a.title.localeCompare(b.title));
}
