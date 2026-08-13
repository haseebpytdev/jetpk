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
  contactPhone?: string;
  city?: string;
  country?: string;
  businessType?: string;
  ntn?: string;
  iataNumber?: string;
  internalNote?: string;
  reviewedAt?: string;
  status: string;
  submittedAt: string;
};

export type CommissionsOverview = {
  kpis: {
    pending: number;
    approvedUnpaid: number;
    paidThisMonth: number;
    activeAgents: number;
  };
  agents: Array<{
    id: string;
    code: string;
    name: string;
    balance: Record<string, unknown>;
  }>;
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

const commissionsService = createReadOnlyService<Record<string, never>, CommissionsOverview>({
  module: "commissions",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(_q, options) {
      return createReadOnlyEnvelope({
        data: {
          kpis: { pending: 0, approvedUnpaid: 0, paidThisMonth: 0, activeAgents: 1 },
          agents: [{ id: "1", code: "AG-PREVIEW", name: "Preview Agent", balance: { available: 0 } }],
        },
        metadata: options?.metadata,
      });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(_q, options) {
      const envelope = await fetchDashboardApi<CommissionsOverview>(DASHBOARD_API_ROUTES.commissions, {
        signal: options?.signal,
      });
      return {
        ...envelope,
        data: {
          kpis: envelope.data?.kpis ?? { pending: 0, approvedUnpaid: 0, paidThisMonth: 0, activeAgents: 0 },
          agents: envelope.data?.agents ?? [],
        },
      };
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

export async function getCommissionsOverview(options?: ReadOnlyFetchOptions): Promise<CommissionsOverview> {
  try {
    return (await commissionsService.fetchReadOnly({}, options)).data;
  } catch (error) {
    mapError(error);
  }
}
