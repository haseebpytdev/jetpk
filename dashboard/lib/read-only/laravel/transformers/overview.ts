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
    bookingPipeline: payload.bookingPipeline.map((stage) => ({
      key: stage.key,
      label: stage.label,
      count: stage.count,
      laravelRoute: stage.laravelRoute,
      queue: stage.queue ?? undefined,
    })),
    shortcutActions: payload.operationalQueues.slice(0, 4).map((card) => ({
      label: card.label,
      laravelRoute: card.laravelRoute,
      queue: card.queue ?? undefined,
    })),
    recentBookings: payload.recentBookings,
    paymentOperations: (payload.paymentOperations ?? []).map((item) => ({
      key: item.key,
      label: item.label,
      count: item.count,
      laravelRoute: item.laravelRoute,
      queue: item.queue ?? undefined,
    })),
    supportOperations: (payload.supportOperations ?? []).map((item) => ({
      key: item.key,
      label: item.label,
      count: item.count,
      laravelRoute: item.laravelRoute,
      queue: item.queue ?? undefined,
      helper: item.helper,
    })),
    supplierStatus: payload.supplierStatus ?? [],
    systemHealth: payload.systemHealth ?? [],
    operationalCounts: payload.operationalCounts ?? {},
  };
}
