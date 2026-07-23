"use client";

import dynamic from "next/dynamic";
import { Skeleton } from "@/components/ui/skeleton";
import type { OverviewData } from "@/types/dashboard";

const Charts = dynamic(
  () => import("@/features/overview/overview-charts").then((m) => m.OverviewCharts),
  {
    loading: () => (
      <div className="grid gap-4 lg:grid-cols-2">
        <Skeleton className="h-72 w-full" />
        <Skeleton className="h-72 w-full" />
      </div>
    ),
    ssr: false,
  },
);

export function OverviewChartsLazy(props: Pick<OverviewData, "bookingTrend" | "statusBreakdown">) {
  return <Charts {...props} />;
}
