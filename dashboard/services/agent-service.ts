import type { AgentRecord, AgentsPageResult, AgentsQuery } from "@/types/agent";
import { buildAgentsPage } from "@/lib/agents-filter";
import { getAgentById, mockAgents } from "@/mocks/agent-fixtures";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { createReadOnlyService, ReadOnlyServiceError, type ReadOnlyFetchOptions } from "@/lib/read-only/read-only-service";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import { transformAgentDetail, transformAgentsPage } from "@/lib/read-only/laravel/transformers/agents";
import type { LaravelAgentsListPayload } from "@/lib/read-only/laravel/types";

export class AgentsServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "AgentsServiceError";
    this.referenceId = referenceId;
  }
}

function mapReadOnlyError(error: unknown): never {
  if (error instanceof ReadOnlyServiceError) {
    throw new AgentsServiceError(error.envelope.error.message, error.envelope.error.referenceIdSafe);
  }
  throw error;
}

function toLaravelQuery(query: AgentsQuery): Record<string, string | number> {
  return {
    page: query.page,
    pageSize: query.pageSize,
    q: query.q,
    status: query.accountStatus,
    agentType: query.agentType,
    verificationStatus: query.verificationStatus,
    commercialStatus: query.commercialStatus,
    settlementStatus: query.settlementStatus,
    operatingRegion: query.countryRegion,
    activityFrom: query.activityFrom,
    activityTo: query.activityTo,
    sort: query.sort,
    direction: query.direction,
  };
}

const agentsService = createReadOnlyService<AgentsQuery, AgentsPageResult>({
  module: "agents",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(query, options) {
      if (query.previewError) {
        throw new ReadOnlyServiceError({
          error: {
            code: "internal_error",
            message: "Mock agent service returned a recoverable error (preview simulation).",
            referenceIdSafe: "AG-PREVIEW-SIM-ERR",
          },
          meta: { source: "fixture", schemaVersion: "dash-read-only-v1" },
        });
      }
      await new Promise((r) => setTimeout(r, 80));
      return createReadOnlyEnvelope({ data: buildAgentsPage(query, mockAgents), metadata: options?.metadata });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(query, options) {
      const envelope = await fetchDashboardApi<LaravelAgentsListPayload>(DASHBOARD_API_ROUTES.agents, {
        signal: options?.signal,
        query: toLaravelQuery(query),
      });
      const pagination = envelope.pagination ?? { page: 1, pageSize: 25, total: 0, pageCount: 1 };
      return { ...envelope, data: transformAgentsPage(envelope.data, pagination) };
    },
  },
});

export async function getAgentsPage(query: AgentsQuery, options?: ReadOnlyFetchOptions): Promise<AgentsPageResult> {
  try {
    const envelope = await agentsService.fetchReadOnly(query, options);
    return envelope.data;
  } catch (error) {
    mapReadOnlyError(error);
  }
}

export async function getAgentDetail(id: string, options?: ReadOnlyFetchOptions): Promise<AgentRecord | null> {
  const { resolveDataSourceMode } = await import("@/lib/read-only/data-source");
  const mode = resolveDataSourceMode();

  if (mode === "fixture") {
    await new Promise((r) => setTimeout(r, 40));
    return getAgentById(id) ?? null;
  }

  try {
    const envelope = await fetchDashboardApi<{ summary: AgentRecord } | AgentRecord>(
      DASHBOARD_API_ROUTES.agentDetail(id),
      { signal: options?.signal },
    );
    return transformAgentDetail(envelope.data);
  } catch (error) {
    if (error instanceof ReadOnlyServiceError && error.envelope.error.code === "not_found") {
      return null;
    }
    mapReadOnlyError(error);
  }
}

export function listAllMockAgents(): AgentRecord[] {
  return mockAgents;
}
