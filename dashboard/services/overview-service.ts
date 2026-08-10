import type { OverviewData } from "@/types/dashboard";
import {
  bookingTrend,
  operationalActionCards,
  recentBookings,
  shortcutActions,
  statusBreakdown,
  summaryStats,
  systemHealth,
} from "@/mocks/overview-fixtures";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { createReadOnlyService, ReadOnlyServiceError } from "@/lib/read-only/read-only-service";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import { transformOverviewPayload } from "@/lib/read-only/laravel/transformers/overview";
import type { LaravelOverviewPayload } from "@/lib/read-only/laravel/types";
import type { ReadOnlyFetchOptions } from "@/lib/read-only/read-only-service";

const overviewService = createReadOnlyService<Record<string, never>, OverviewData>({
  module: "overview",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(_query, options) {
      await new Promise((r) => setTimeout(r, 120));
      return createReadOnlyEnvelope({
        data: {
          summaryStats,
          operationalActionCards,
          shortcutActions,
          bookingPipeline: operationalActionCards.slice(0, 5).map((card) => ({
            key: card.key,
            label: card.label,
            count: card.count,
            laravelRoute: card.laravelRoute,
            queue: card.queue,
          })),
          recentBookings,
          paymentOperations: operationalActionCards
            .filter((card) => card.key === "payment_review" || card.key === "pending_deposits")
            .map((card) => ({
              key: card.key,
              label: card.label,
              count: card.count,
              laravelRoute: card.laravelRoute,
              queue: card.queue,
            })),
          supportOperations: [],
          supplierStatus: [
            { key: "sabre", label: "Sabre GDS", status: "operational", detail: "Fixture preview" },
          ],
          systemHealth,
        },
        metadata: options?.metadata,
      });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(_query, options) {
      const envelope = await fetchDashboardApi<LaravelOverviewPayload>(DASHBOARD_API_ROUTES.overview, {
        signal: options?.signal,
      });
      return { ...envelope, data: transformOverviewPayload(envelope.data) };
    },
  },
});

export async function getOverviewData(options?: ReadOnlyFetchOptions): Promise<OverviewData> {
  const envelope = await overviewService.fetchReadOnly({}, options);
  return envelope.data;
}

export { ReadOnlyServiceError as OverviewServiceError };
