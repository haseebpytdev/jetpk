import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { createReadOnlyService, ReadOnlyServiceError, type ReadOnlyFetchOptions } from "@/lib/read-only/read-only-service";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";

export type MarkupRecord = {
  id: string;
  name: string;
  ruleType: string;
  value: string;
  valueType: string;
  priority: number;
  status: string;
  isActive: boolean;
};

export type AgentApplicationRecord = {
  id: string;
  agencyName: string;
  contactName: string;
  contactEmail: string;
  status: string;
  submittedAt: string;
};

const mockMarkups: MarkupRecord[] = [
  {
    id: "1",
    name: "Default economy markup",
    ruleType: "percentage",
    value: "2.5",
    valueType: "percent",
    priority: 10,
    status: "active",
    isActive: true,
  },
];

const mockApplications: AgentApplicationRecord[] = [
  {
    id: "1",
    agencyName: "Preview Travel Agency",
    contactName: "Preview Contact",
    contactEmail: "preview@example.com",
    status: "pending",
    submittedAt: new Date().toISOString(),
  },
];

function mapError(error: unknown): never {
  if (error instanceof ReadOnlyServiceError) {
    throw error;
  }
  throw error;
}

const markupsService = createReadOnlyService<Record<string, never>, MarkupRecord[]>({
  module: "markups",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(_q, options) {
      return createReadOnlyEnvelope({ data: mockMarkups, metadata: options?.metadata });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(_q, options) {
      const envelope = await fetchDashboardApi<{ markups: MarkupRecord[] }>(DASHBOARD_API_ROUTES.markups, {
        signal: options?.signal,
      });
      return { ...envelope, data: envelope.data?.markups ?? [] };
    },
  },
});

const applicationsService = createReadOnlyService<Record<string, never>, AgentApplicationRecord[]>({
  module: "agent-applications",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(_q, options) {
      return createReadOnlyEnvelope({ data: mockApplications, metadata: options?.metadata });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(_q, options) {
      const envelope = await fetchDashboardApi<{ applications: AgentApplicationRecord[] }>(
        DASHBOARD_API_ROUTES.agentApplications,
        { signal: options?.signal },
      );
      return { ...envelope, data: envelope.data?.applications ?? [] };
    },
  },
});

export async function getMarkups(options?: ReadOnlyFetchOptions): Promise<MarkupRecord[]> {
  try {
    return (await markupsService.fetchReadOnly({}, options)).data;
  } catch (error) {
    mapError(error);
  }
}

export async function getAgentApplications(options?: ReadOnlyFetchOptions): Promise<AgentApplicationRecord[]> {
  try {
    return (await applicationsService.fetchReadOnly({}, options)).data;
  } catch (error) {
    mapError(error);
  }
}
