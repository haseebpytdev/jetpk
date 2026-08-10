import { Suspense } from "react";
import { OperationalQueueGrid } from "@/features/overview/operational-queue";
import {
  BookingPipelinePanel,
  PaymentOperationsPanel,
  SupplierStatusPanel,
  SupportOperationsPanel,
  SystemHealthPanel,
} from "@/features/overview/operational-dashboard-panels";
import { RecentBookingsTable, SummaryStatsRow } from "@/features/overview/overview-panels";
import { getOverviewData, OverviewServiceError } from "@/services/overview-service";
import { OverviewToolbarActions } from "@/components/dashboard/overview-toolbar";
import { Breadcrumb, PageContainer, PageHeader } from "@/components/ui/page-layout";
import {
  ForbiddenState,
  SanitizedErrorState,
  ServiceUnavailableState,
  UnauthorizedState,
} from "@/components/ui/data-source-status";
import { ReadOnlyServiceError } from "@/lib/read-only/read-only-service";

export async function OverviewPageContent() {
  try {
    const data = await getOverviewData();

    return (
      <PageContainer>
        <PageHeader
          breadcrumb={<Breadcrumb items={[{ label: "Home" }, { label: "Dashboard" }]} />}
          title="Operations dashboard"
          description="Live workload, booking pipeline, and supplier posture."
          actions={<OverviewToolbarActions />}
        />

        <SummaryStatsRow summaryStats={data.summaryStats} />

        <div className="grid gap-4 xl:grid-cols-3">
          <div className="space-y-4 xl:col-span-2">
            <OperationalQueueGrid cards={data.operationalActionCards} />
            <RecentBookingsTable recentBookings={data.recentBookings} />
          </div>
          <div className="space-y-4">
            <Suspense fallback={null}>
              <BookingPipelinePanel stages={data.bookingPipeline} />
            </Suspense>
            <PaymentOperationsPanel items={data.paymentOperations} />
            <SupportOperationsPanel items={data.supportOperations} />
          </div>
        </div>

        <div className="grid gap-4 lg:grid-cols-2">
          <SupplierStatusPanel items={data.supplierStatus} />
          <SystemHealthPanel items={data.systemHealth} />
        </div>
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
