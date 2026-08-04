import { OperationalExecutionWorkspace } from "@/features/execution/operational-execution-workspace";
import { PageHeader } from "@/components/ui/page-layout";
import {
  mockCancellationExecutions,
  mockRefundExecutions,
  mockTicketingExecutions,
} from "@/mocks/execution-fixtures";

export const metadata = {
  title: "Operational execution — JetPakistan Dashboard",
};

export default function OperationalExecutionPage() {
  return (
    <div className="space-y-6">
      <PageHeader
        title="Operational execution"
        description="Authoritative cancellation, refund settlement, and ticket issuance controls."
      />
      <OperationalExecutionWorkspace
        cancellations={mockCancellationExecutions}
        refunds={mockRefundExecutions}
        ticketing={mockTicketingExecutions}
      />
    </div>
  );
}
