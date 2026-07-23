import { Suspense } from "react";
import { OperationalQueueGrid } from "@/features/overview/operational-queue";
import { OverviewChartsLazy } from "@/features/overview/overview-charts-lazy";
import {
  QuickActionsBar,
  RecentBookingsTable,
  RecentNotificationsPanel,
  SidePanels,
  SummaryStatsRow,
} from "@/features/overview/overview-panels";
import { getOverviewData } from "@/services/overview-service";
import { OverviewToolbarActions } from "@/components/dashboard/overview-toolbar";
import { Card } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";

export async function OverviewPageContent() {
  const data = await getOverviewData();

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <nav aria-label="Breadcrumb" className="text-xs text-jp-muted">
            <ol className="flex gap-1">
              <li>Home</li>
              <li aria-hidden>/</li>
              <li className="text-gray-900">Dashboard</li>
            </ol>
          </nav>
          <h1 className="mt-2 font-display text-2xl font-bold tracking-tight sm:text-3xl">Dashboard</h1>
          <p className="mt-1 text-sm text-jp-muted">
            Welcome back — preview overview with operational priorities (mock data).
          </p>
        </div>
        <OverviewToolbarActions />
      </div>

      <Card className="border-emerald-200 bg-emerald-50/60 text-sm text-emerald-900">
        Preview mode — all metrics are synthetic. No production PNRs, payments, or customer data.
      </Card>

      <SummaryStatsRow summaryStats={data.summaryStats} />

      <OperationalQueueGrid cards={data.operationalActionCards} />

      <div className="grid gap-4 xl:grid-cols-3">
        <div className="space-y-4 xl:col-span-2">
          <Suspense
            fallback={
              <div className="grid gap-4 lg:grid-cols-2">
                <Skeleton className="h-72" />
                <Skeleton className="h-72" />
              </div>
            }
          >
            <OverviewChartsLazy bookingTrend={data.bookingTrend} statusBreakdown={data.statusBreakdown} />
          </Suspense>
          <RecentBookingsTable recentBookings={data.recentBookings} />
        </div>
        <div className="space-y-4">
          <RecentNotificationsPanel recentNotifications={data.recentNotifications} />
          <SidePanels topRoutes={data.topRoutes} systemHealth={data.systemHealth} />
        </div>
      </div>

      <QuickActionsBar actions={data.shortcutActions} />
    </div>
  );
}
