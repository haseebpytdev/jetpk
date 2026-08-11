import { OperationalBookingsQueue } from "@/features/operations/operational-bookings-queue";
import { OperationalExecutionWorkspace } from "@/features/execution/operational-execution-workspace";
import { Breadcrumb, PageContainer, PageHeader } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import {
  ForbiddenState,
  SanitizedErrorState,
  ServiceUnavailableState,
  UnauthorizedState,
} from "@/components/ui/data-source-status";
import { resolveDataSourceMode } from "@/lib/read-only/data-source";
import { ReadOnlyServiceError } from "@/lib/read-only/read-only-service";
import { defaultBookingsQuery } from "@/lib/bookings-query";
import { BookingsServiceError, getBookingsPage } from "@/services/booking-service";
import {
  mockCancellationExecutions,
  mockRefundExecutions,
  mockTicketingExecutions,
} from "@/mocks/execution-fixtures";

export const metadata = {
  title: "Operational execution — JetPakistan Dashboard",
};

export default async function OperationalExecutionPage() {
  const mode = resolveDataSourceMode();

  if (mode === "fixture") {
    return (
      <PageContainer>
        <PreviewModeBadgeSlot />
        <PageHeader
          title="Operational execution"
          description="Preview shell for cancellation, refund settlement, and ticket issuance controls."
        />
        <OperationalExecutionWorkspace
          cancellations={mockCancellationExecutions}
          refunds={mockRefundExecutions}
          ticketing={mockTicketingExecutions}
        />
      </PageContainer>
    );
  }

  try {
    const result = await getBookingsPage({
      ...defaultBookingsQuery(),
      queue: "needs_action",
      pageSize: 50,
    });

    return (
      <PageContainer>
        <PreviewModeBadgeSlot />
        <PageHeader
          breadcrumb={
            <Breadcrumb items={[{ label: "Home" }, { label: "Operations" }, { label: "Execution" }]} />
          }
          title="Operational execution"
          description="Needs-action queue. Open booking management for intake actions. Commercial supplier mutations remain backend-proven (AD-009)."
        />
        <DataSourceNoticeSlot />
        <OperationalBookingsQueue
          queue="needs_action"
          title="Needs action"
          description="Bookings requiring payment review, PNR, ticketing, cancellation, or refund attention."
          result={result}
          testId="operational-execution-queue"
        />
      </PageContainer>
    );
  } catch (error) {
    return (
      <PageContainer>
        <PageHeader title="Operational execution" />
        <QueueError error={error} />
      </PageContainer>
    );
  }
}

function QueueError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="bookings" />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  if (error instanceof BookingsServiceError) {
    return <SanitizedErrorState message={error.message} referenceId={error.referenceId} />;
  }
  throw error;
}
