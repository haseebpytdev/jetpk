import type {
  AgentRecord,
  AgentsPageResult,
  AgentsQuery,
  AgentsSummaryMetrics,
} from "@/types/agent";
import { mockAgents } from "@/mocks/agent-fixtures";

function matchesSearch(agent: AgentRecord, q: string): boolean {
  if (!q) return true;
  const needle = q.toLowerCase();
  const haystack = [
    agent.id,
    agent.agencyName,
    agent.tradingName,
    agent.primaryContact,
    agent.email,
    agent.phone,
    agent.city,
    agent.country,
    agent.operatingRegion,
    agent.supportOwner,
    agent.notesSummary,
    ...agent.linkedBookingIds,
    ...agent.linkedCustomerIds,
    ...agent.linkedTransactionIds,
    ...agent.linkedPnrIds,
    ...agent.linkedTicketIds,
  ]
    .join(" ")
    .toLowerCase();
  return haystack.includes(needle);
}

function inDateRange(value: string | null, from: string, to: string): boolean {
  if (!value) return !from && !to;
  if (from && value < from) return false;
  if (to && value > to) return false;
  return true;
}

function activityDate(agent: AgentRecord): string | null {
  return agent.lastBookingDate ?? agent.lastPaymentDate ?? agent.lastTicketActivity ?? agent.createdDate;
}

export function filterAgents(all: AgentRecord[], query: AgentsQuery): AgentRecord[] {
  return all.filter((agent) => {
    if (!matchesSearch(agent, query.q)) return false;
    if (query.accountStatus !== "all" && agent.accountStatus !== query.accountStatus) return false;
    if (query.verificationStatus !== "all" && agent.verificationStatus !== query.verificationStatus)
      return false;
    if (query.commercialStatus !== "all" && agent.commercialStatus !== query.commercialStatus)
      return false;
    if (query.settlementStatus !== "all" && agent.settlementStatus !== query.settlementStatus)
      return false;
    if (query.agentType !== "all" && agent.agentType !== query.agentType) return false;
    if (query.city && agent.city !== query.city) return false;
    if (query.countryRegion) {
      const regionMatch =
        agent.country === query.countryRegion || agent.operatingRegion === query.countryRegion;
      if (!regionMatch) return false;
    }
    if (query.hasOutstandingBalance === "yes" && agent.outstandingCustomerBalance <= 0) return false;
    if (query.hasOutstandingBalance === "no" && agent.outstandingCustomerBalance > 0) return false;
    if (query.hasPendingCommission === "yes" && agent.commissionPending <= 0) return false;
    if (query.hasPendingCommission === "no" && agent.commissionPending > 0) return false;
    if (query.hasBookings === "yes" && agent.bookingCount === 0) return false;
    if (query.hasBookings === "no" && agent.bookingCount > 0) return false;
    if (!inDateRange(activityDate(agent), query.activityFrom, query.activityTo)) return false;
    return true;
  });
}

function compareStrings(a: string, b: string): number {
  return a.localeCompare(b, "en", { sensitivity: "base" });
}

function compareNullableDates(a: string | null, b: string | null): number {
  if (!a && !b) return 0;
  if (!a) return 1;
  if (!b) return -1;
  return compareStrings(a, b);
}

const STATUS_PRIORITY: Record<string, number> = {
  Active: 0,
  "Review Required": 1,
  Inactive: 2,
  Suspended: 3,
};

export function sortAgents(
  rows: AgentRecord[],
  sort: AgentsQuery["sort"],
  direction: AgentsQuery["direction"],
): AgentRecord[] {
  const sorted = [...rows].sort((a, b) => {
    let cmp = 0;
    switch (sort) {
      case "agentName":
        cmp = compareStrings(a.agencyName, b.agencyName);
        break;
      case "newest":
        cmp = compareStrings(b.createdDate, a.createdDate);
        break;
      case "bookingCount":
        cmp = a.bookingCount - b.bookingCount;
        break;
      case "grossBookingValue":
        cmp = a.grossBookingValue - b.grossBookingValue;
        break;
      case "totalPaid":
        cmp = a.totalPaid - b.totalPaid;
        break;
      case "outstandingBalance":
        cmp = a.outstandingCustomerBalance - b.outstandingCustomerBalance;
        break;
      case "commissionPending":
        cmp = a.commissionPending - b.commissionPending;
        break;
      case "lastBookingDate":
        cmp = compareNullableDates(a.lastBookingDate, b.lastBookingDate);
        break;
      case "statusPriority":
        cmp = (STATUS_PRIORITY[a.accountStatus] ?? 99) - (STATUS_PRIORITY[b.accountStatus] ?? 99);
        break;
      default:
        cmp = 0;
    }
    if (cmp === 0) {
      cmp = compareStrings(a.id, b.id);
    }
    return direction === "asc" ? cmp : -cmp;
  });
  return sorted;
}

export function computeAgentsSummary(rows: AgentRecord[]): AgentsSummaryMetrics {
  let activeAgents = 0;
  let verifiedAgents = 0;
  let agentsWithOverdueBalances = 0;
  let grossBookingValue = 0;
  let pendingCommission = 0;

  for (const agent of rows) {
    if (agent.accountStatus === "Active") activeAgents += 1;
    if (agent.verificationStatus === "Verified") verifiedAgents += 1;
    if (agent.settlementStatus === "Overdue" || agent.outstandingCustomerBalance > 0) {
      agentsWithOverdueBalances += 1;
    }
    grossBookingValue += agent.grossBookingValue;
    pendingCommission += agent.commissionPending;
  }

  return {
    totalAgents: rows.length,
    activeAgents,
    verifiedAgents,
    agentsWithOverdueBalances,
    grossBookingValue,
    pendingCommission,
    currency: "PKR",
  };
}

export function paginateAgents(
  rows: AgentRecord[],
  page: number,
  pageSize: number,
): { page: number; pageCount: number; slice: AgentRecord[] } {
  const pageCount = Math.max(1, Math.ceil(rows.length / pageSize));
  const clampedPage = Math.min(Math.max(1, page), pageCount);
  const start = (clampedPage - 1) * pageSize;
  return {
    page: clampedPage,
    pageCount,
    slice: rows.slice(start, start + pageSize),
  };
}

export function buildAgentsPage(
  query: AgentsQuery,
  all: AgentRecord[] = mockAgents,
): AgentsPageResult {
  const filtered = filterAgents(all, query);
  const sorted = sortAgents(filtered, query.sort, query.direction);
  const { page, pageCount, slice } = paginateAgents(sorted, query.page, query.pageSize);
  const cities = [...new Set(all.map((a) => a.city))].sort();
  const countries = [...new Set(all.map((a) => a.country))].sort();
  const regions = [...new Set(all.map((a) => a.operatingRegion))].sort();
  const agentTypes = [...new Set(all.map((a) => a.agentType))].sort();

  return {
    agents: slice,
    total: filtered.length,
    page,
    pageSize: query.pageSize,
    pageCount,
    summary: computeAgentsSummary(filtered),
    facets: { cities, countries, regions, agentTypes },
  };
}

export function countActiveAgentFilters(query: AgentsQuery): number {
  let n = 0;
  if (query.q) n += 1;
  if (query.accountStatus !== "all") n += 1;
  if (query.verificationStatus !== "all") n += 1;
  if (query.commercialStatus !== "all") n += 1;
  if (query.settlementStatus !== "all") n += 1;
  if (query.agentType !== "all") n += 1;
  if (query.city) n += 1;
  if (query.countryRegion) n += 1;
  if (query.hasOutstandingBalance !== "all") n += 1;
  if (query.hasPendingCommission !== "all") n += 1;
  if (query.hasBookings !== "all") n += 1;
  if (query.activityFrom || query.activityTo) n += 1;
  return n;
}
