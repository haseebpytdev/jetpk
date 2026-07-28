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
import { getOverviewData, OverviewServiceError } from "@/services/overview-service";
import { OverviewToolbarActions } from "@/components/dashboard/overview-toolbar";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { Breadcrumb, PageContainer, PageHeader } from "@/components/ui/page-layout";
import {
  ForbiddenState,
  SanitizedErrorState,
  ServiceUnavailableState,
  UnauthorizedState,
} from "@/components/ui/data-source-status";
import { Skeleton } from "@/components/ui/skeleton";
import { ReadOnlyServiceError } from "@/lib/read-only/read-only-service";

export async function OverviewPageContent() {
  try {
    const data = await getOverviewData();

    return (
      <PageContainer>
        <PreviewModeBadgeSlot />
        <PageHeader
          breadcrumb={<Breadcrumb items={[{ label: "Home" }, { label: "Dashboard" }]} />}
          title="Dashboard"
          description="Operational overview with read-only data from the configured source."
          actions={<OverviewToolbarActions />}
        />
        <DataSourceNoticeSlot />

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
      </PageContainer>
    );
  } catch (error) {
    return (
      <PageContainer>
        <PageHeader title="Dashboard" />
        <OverviewErrorState error={error} />
      </PageContainer>
    );
  }
}

function OverviewErrorState({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="dashboard overview" />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  if (error instanceof OverviewServiceError) {
    return <SanitizedErrorState message={error.message} referenceId="OV-ERROR" />;
  }
  return <SanitizedErrorState message="Something went wrong while loading data." referenceId="OV-UNKNOWN" />;
}
