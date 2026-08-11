import { OperationalBookingsQueue } from "@/features/operations/operational-bookings-queue";
import { OperationalReviewWorkspace } from "@/features/review/operational-review-workspace";
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
import { mockCancellationReviews, mockRefundReviews } from "@/mocks/review-fixtures";

export const metadata = {
  title: "Operational review — JetPakistan Dashboard",
};

export default async function OperationalReviewPage() {
  const mode = resolveDataSourceMode();

  if (mode === "fixture") {
    return (
      <PageContainer>
        <PreviewModeBadgeSlot />
        <PageHeader
          title="Operational review"
          description="Preview shell for cancellation and refund review."
        />
        <OperationalReviewWorkspace cancellations={mockCancellationReviews} refunds={mockRefundReviews} />
      </PageContainer>
    );
  }

  try {
    const result = await getBookingsPage({
      ...defaultBookingsQuery(),
      queue: "cancellations",
      pageSize: 50,
    });

    return (
      <PageContainer>
        <PreviewModeBadgeSlot />
        <PageHeader
          breadcrumb={
            <Breadcrumb items={[{ label: "Home" }, { label: "Operations" }, { label: "Cancellations" }]} />
          }
          title="Operational review"
          description="Cancellation review queue. Open booking management to approve or process requests. Commercial mutations remain AD-009 constrained."
        />
        <DataSourceNoticeSlot />
        <OperationalBookingsQueue
          queue="cancellations"
          title="Cancellation review"
          description="Bookings with requested or approved cancellation work items."
          result={result}
          testId="operational-review-queue"
        />
      </PageContainer>
    );
  } catch (error) {
    return (
      <PageContainer>
        <PageHeader title="Operational review" />
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
