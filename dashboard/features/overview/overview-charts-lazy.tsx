"use client";

import dynamic from "next/dynamic";
import { useSyncExternalStore } from "react";
import { Skeleton } from "@/components/ui/skeleton";
import type { OverviewData } from "@/types/dashboard";

function OverviewChartsSkeleton() {
  return (
    <div className="grid gap-4 lg:grid-cols-2" data-testid="overview-charts-skeleton">
      <Skeleton className="h-72 w-full" />
      <Skeleton className="h-72 w-full" />
    </div>
  );
}

const Charts = dynamic(
  () => import("@/features/overview/overview-charts").then((m) => m.OverviewCharts),
  {
    loading: () => <OverviewChartsSkeleton />,
    ssr: false,
  },
);

function subscribeHydrated() {
  return () => {};
}

function getHydratedSnapshot() {
  return true;
}

function getHydratedServerSnapshot() {
  return false;
}

export function OverviewChartsLazy(props: Pick<OverviewData, "bookingTrend" | "statusBreakdown">) {
  const hydrated = useSyncExternalStore(subscribeHydrated, getHydratedSnapshot, getHydratedServerSnapshot);

  if (!hydrated) {
    return <OverviewChartsSkeleton />;
  }

  return <Charts {...props} />;
}
