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
import { useMockData } from "@/lib/preview";

export async function getOverviewData(): Promise<OverviewData> {
  if (!useMockData()) {
    throw new Error("Live data is disabled in preview. Set NEXT_PUBLIC_USE_MOCK_DATA=true.");
  }

  await new Promise((r) => setTimeout(r, 120));

  return {
    summaryStats,
    operationalActionCards,
    shortcutActions,
    bookingTrend,
    statusBreakdown,
    recentNotifications,
    recentBookings,
    topRoutes,
    systemHealth,
  };
}
