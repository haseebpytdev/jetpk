import { OperationalReviewWorkspace } from "@/features/review/operational-review-workspace";
import { PageHeader } from "@/components/ui/page-layout";
import { mockCancellationReviews, mockRefundReviews } from "@/mocks/review-fixtures";

export const metadata = {
  title: "Operational review — JetPakistan Dashboard",
};

export default function OperationalReviewPage() {
  return (
    <div className="space-y-6">
      <PageHeader
        title="Operational review"
        description="Approve or reject cancellation and refund requests before execution or settlement."
      />
      <OperationalReviewWorkspace cancellations={mockCancellationReviews} refunds={mockRefundReviews} />
    </div>
  );
}
