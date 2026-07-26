import type { BookingRecord } from "@/types/booking";
import type { TransactionRecord } from "@/types/payment";
import type { PnrRecord } from "@/types/pnr";
import type { TicketRecord } from "@/types/ticket";
import type { ReportDateRange, ReportsQuery } from "@/types/report";
import { filterByCurrency } from "@/lib/reports/currency";
import type { OperationalFixtureGraph } from "@/lib/reports/aggregations";

function inDateRange(isoDate: string, range: ReportDateRange): boolean {
  return isoDate >= range.startDate && isoDate <= range.endDate;
}

export function bookingChannel(booking: BookingRecord): string {
  if (booking.agentOrSource.toLowerCase().includes("agent")) return "agent";
  if (booking.agentOrSource.toLowerCase().includes("mobile")) return "mobile";
  if (booking.agentOrSource.toLowerCase().includes("api")) return "api";
  return "web";
}

export function bookingRoute(booking: BookingRecord): string {
  return `${booking.origin}–${booking.destination}`;
}

export function bookingAgentName(booking: BookingRecord): string {
  if (!booking.agentOrSource.toLowerCase().includes("agent")) return "";
  return booking.agentOrSource.replace(/^Agent\s*—\s*/i, "").trim();
}

function matchesChannel(channel: string, value: string): boolean {
  if (!channel) return true;
  return value === channel;
}

function matchesText(filter: string, value: string): boolean {
  if (!filter) return true;
  return value.toLowerCase() === filter.toLowerCase();
}

export function filterBookingsByQuery(
  bookings: BookingRecord[],
  range: ReportDateRange,
  query: ReportsQuery,
): BookingRecord[] {
  let rows = bookings.filter((b) => inDateRange(b.bookingDate, range));
  rows = filterByCurrency(rows, query.currency);
  if (query.channel) rows = rows.filter((b) => matchesChannel(query.channel, bookingChannel(b)));
  if (query.supplier) rows = rows.filter((b) => matchesText(query.supplier, b.supplier));
  if (query.airline) rows = rows.filter((b) => matchesText(query.airline, b.airline));
  if (query.agent) rows = rows.filter((b) => matchesText(query.agent, bookingAgentName(b)));
  if (query.route) rows = rows.filter((b) => matchesText(query.route, bookingRoute(b)));
  if (query.bookingStatus !== "all") rows = rows.filter((b) => b.bookingStatus === query.bookingStatus);
  if (query.paymentStatus !== "all") rows = rows.filter((b) => b.paymentStatus === query.paymentStatus);
  return rows;
}

export function filterPaymentsByQuery(
  payments: TransactionRecord[],
  range: ReportDateRange,
  query: ReportsQuery,
  bookingIds?: Set<string>,
): TransactionRecord[] {
  let rows = payments.filter((p) => inDateRange(p.transactionDate, range));
  rows = filterByCurrency(rows, query.currency);
  if (bookingIds) rows = rows.filter((p) => bookingIds.has(p.bookingId));
  if (query.channel) rows = rows.filter((p) => matchesChannel(query.channel, p.paymentChannel));
  if (query.paymentStatus !== "all") rows = rows.filter((p) => p.paymentStatus === query.paymentStatus);
  return rows;
}

export function filterPnrsByQuery(
  pnrs: PnrRecord[],
  range: ReportDateRange,
  query: ReportsQuery,
  bookingIds?: Set<string>,
): PnrRecord[] {
  let rows = pnrs.filter((p) => inDateRange(p.createdDate, range));
  rows = filterByCurrency(rows, query.currency);
  if (bookingIds) rows = rows.filter((p) => bookingIds.has(p.bookingId));
  if (query.supplier) rows = rows.filter((p) => matchesText(query.supplier, p.supplierName));
  if (query.airline) rows = rows.filter((p) => matchesText(query.airline, p.airline));
  if (query.agent) rows = rows.filter((p) => (p.agentName ? matchesText(query.agent, p.agentName) : false));
  if (query.route) rows = rows.filter((p) => matchesText(query.route, `${p.origin}–${p.destination}`));
  if (query.channel) {
    const channelMap: Record<string, string> = {
      web: "Manual",
      agent: "Sabre GDS",
      mobile: "One API",
      api: "One API",
    };
    const mapped = channelMap[query.channel];
    if (mapped) rows = rows.filter((p) => p.channel === mapped || p.channel === query.channel);
    else rows = rows.filter((p) => p.channel.toLowerCase().includes(query.channel.toLowerCase()));
  }
  if (query.ticketStatus !== "all") rows = rows.filter((p) => p.ticketingStatus === query.ticketStatus);
  if (query.fulfilmentStatus !== "all") rows = rows.filter((p) => p.fulfilmentStatus === query.fulfilmentStatus);
  return rows;
}

export function filterTicketsByQuery(
  tickets: TicketRecord[],
  range: ReportDateRange,
  query: ReportsQuery,
  bookingIds?: Set<string>,
): TicketRecord[] {
  let rows = tickets.filter((t) => t.issueDate && inDateRange(t.issueDate, range));
  if (bookingIds) rows = rows.filter((t) => bookingIds.has(t.bookingId));
  if (query.ticketStatus !== "all") rows = rows.filter((t) => t.issueStatus === query.ticketStatus);
  return rows;
}

export function buildReportFacets(graph: OperationalFixtureGraph) {
  const agents = new Set<string>();
  const routes = new Set<string>();
  for (const b of graph.bookings) {
    const agent = bookingAgentName(b);
    if (agent) agents.add(agent);
    routes.add(bookingRoute(b));
  }
  const suppliers = [...new Set(graph.bookings.map((b) => b.supplier))].sort();
  const airlines = [...new Set(graph.bookings.map((b) => b.airline))].sort();
  return {
    suppliers,
    airlines,
    agents: [...agents].sort(),
    routes: [...routes].sort(),
    channels: [
      { value: "", label: "All channels" },
      { value: "web", label: "Web direct" },
      { value: "agent", label: "Agent-assisted" },
      { value: "mobile", label: "Mobile" },
      { value: "api", label: "API" },
    ],
  };
}

export function filteredGraph(
  graph: OperationalFixtureGraph,
  range: ReportDateRange,
  query: ReportsQuery,
) {
  const bookings = filterBookingsByQuery(graph.bookings, range, query);
  const bookingIds = new Set(bookings.map((b) => b.id));
  const payments = filterPaymentsByQuery(graph.payments, range, query, bookingIds);
  const pnrs = filterPnrsByQuery(graph.pnrs, range, query, bookingIds);
  const tickets = filterTicketsByQuery(graph.tickets, range, query, bookingIds);
  return { bookings, payments, pnrs, tickets, bookingIds };
}
