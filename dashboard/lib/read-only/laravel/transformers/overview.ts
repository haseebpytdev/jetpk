import type { OverviewData } from "@/types/dashboard";
import type { LaravelOverviewPayload } from "@/lib/read-only/laravel/types";

export function transformOverviewPayload(payload: LaravelOverviewPayload): OverviewData {
  return {
    summaryStats: payload.summaryStats.map((stat) => ({
      key: stat.key,
      label: stat.label,
      value: stat.value,
      delta: stat.delta || "—",
      tone: (stat.tone as "up" | "down" | "warn") ?? "up",
    })),
    operationalActionCards: payload.operationalQueues.map((card) => ({
      key: card.key,
      label: card.label,
      count: card.count,
      helper: card.helper,
      laravelRoute: card.laravelRoute,
      queue: card.queue ?? undefined,
      tone: card.tone,
      cta: card.cta,
    })),
    shortcutActions: payload.operationalQueues.slice(0, 4).map((card) => ({
      label: card.label,
      laravelRoute: card.laravelRoute,
      queue: card.queue ?? undefined,
    })),
    bookingTrend: [],
    statusBreakdown: [],
    recentNotifications: [],
    recentBookings: payload.recentBookings,
    topRoutes: [],
    systemHealth: payload.hasLiveData
      ? [{ name: "Laravel read-only", status: "operational" as const }]
      : [{ name: "No live bookings", status: "degraded" as const }],
  };
}
