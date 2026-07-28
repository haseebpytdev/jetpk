import type { OverviewData } from "@/types/dashboard";
import {
  bookingTrend,
  operationalActionCards,
  recentBookings,
  recentNotifications,
  shortcutActions,
  statusBreakdown,
  summaryStats,
  systemHealth,
  topRoutes,
} from "@/mocks/overview-fixtures";
import { buildBookingsPage } from "@/lib/bookings-filter";
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
          bookingTrend,
          statusBreakdown,
          recentNotifications,
          recentBookings,
          topRoutes,
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
