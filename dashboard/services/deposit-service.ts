import { createReadOnlyService, type ReadOnlyFetchOptions } from "@/lib/read-only/read-only-service";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { mockDeposits } from "@/mocks/deposits-fixtures";

export type DepositRecord = {
  id: string;
  reference: string;
  status: string;
  amount: number;
  currency: string;
  agencyName: string;
  agentName: string;
  submittedAt: string;
  reviewedAt?: string | null;
  adminNote?: string | null;
  capabilities?: {
    can_approve?: boolean;
    can_reject?: boolean;
    already_processed?: boolean;
  } | null;
};

export type DepositsPageResult = {
  deposits: DepositRecord[];
  total: number;
};

const depositsService = createReadOnlyService<Record<string, never>, DepositsPageResult>({
  module: "deposits",
  fixtureAdapter: {
    mode: "fixture",
    async fetch() {
      return createReadOnlyEnvelope({
        data: { deposits: mockDeposits, total: mockDeposits.length },
      });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(_query, options) {
      const envelope = await fetchDashboardApi<{ deposits: DepositRecord[] }>(DASHBOARD_API_ROUTES.deposits, {
        signal: options?.signal,
      });
      return {
        ...envelope,
        data: {
          deposits: envelope.data.deposits ?? [],
          total: envelope.data.deposits?.length ?? 0,
        },
      };
    },
  },
});

export async function getDepositsPage(options?: ReadOnlyFetchOptions): Promise<DepositsPageResult> {
  const envelope = await depositsService.fetchReadOnly({}, options);
  return envelope.data;
}
