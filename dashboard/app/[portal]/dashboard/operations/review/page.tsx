import { OperationalReviewWorkspace } from "@/features/review/operational-review-workspace";
import { PageHeader } from "@/components/ui/page-layout";
import { mockCancellationReviews, mockRefundReviews } from "@/mocks/review-fixtures";
import { resolveDataSourceMode } from "@/lib/read-only/data-source";

export const metadata = {
  title: "Operations review — JetPakistan Dashboard",
};

export default function OperationsReviewPage() {
  const mode = resolveDataSourceMode();
  // Live must never bind fixture cancellation/refund IDs into mutation controls.
  const cancellations = mode === "fixture" ? mockCancellationReviews : [];
  const refunds = mode === "fixture" ? mockRefundReviews : [];

  return (
    <div className="space-y-6">
      <PageHeader
        title="Operations review"
        description={
          mode === "fixture"
            ? "Fixture review queue for layout testing."
            : "Live Laravel-backed cancellation and refund review queues. Fixture mutation IDs are disabled."
        }
      />
      <OperationalReviewWorkspace cancellations={cancellations} refunds={refunds} />
    </div>
  );
}
