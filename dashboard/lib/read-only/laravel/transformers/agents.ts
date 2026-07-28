import type { AgentsPageResult } from "@/types/agent";
import type { LaravelAgentsListPayload } from "@/lib/read-only/laravel/types";

export function transformAgentsPage(
  payload: LaravelAgentsListPayload,
  pagination: { page: number; pageSize: number; total: number; pageCount: number },
): AgentsPageResult {
  return {
    agents: payload.agents,
    total: pagination.total,
    page: pagination.page,
    pageSize: pagination.pageSize,
    pageCount: pagination.pageCount,
    summary: {
      totalAgents: Number(payload.summary?.totalAgents ?? pagination.total),
      activeAgents: Number(payload.summary?.activeAgents ?? 0),
      verifiedAgents: Number(payload.summary?.verifiedAgents ?? 0),
      agentsWithOverdueBalances: 0,
      grossBookingValue: 0,
      pendingCommission: 0,
      currency: String(payload.summary?.currency ?? "PKR"),
    },
    facets: {
      cities: [],
      countries: [],
      regions: [],
      agentTypes: [],
    },
  };
}

export function transformAgentDetail(payload: { summary: import("@/types/agent").AgentRecord } | import("@/types/agent").AgentRecord) {
  if ("summary" in payload && payload.summary) {
    return payload.summary;
  }
  return payload as import("@/types/agent").AgentRecord;
}
