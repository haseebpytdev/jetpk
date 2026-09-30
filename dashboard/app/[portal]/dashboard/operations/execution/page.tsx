import { OperationalExecutionWorkspace } from "@/features/execution/operational-execution-workspace";
import { PageHeader } from "@/components/ui/page-layout";
import {
  mockCancellationExecutions,
  mockRefundExecutions,
  mockTicketingExecutions,
} from "@/mocks/execution-fixtures";
import { resolveDataSourceMode } from "@/lib/read-only/data-source";

export const metadata = {
  title: "Operational execution — JetPakistan Dashboard",
};

export default function OperationalExecutionPage() {
  const mode = resolveDataSourceMode();
  // Live must never expose supplier-adjacent process/issue controls bound to fixture IDs.
  const liveSafe = mode !== "fixture";

  return (
    <div className="space-y-6">
      <PageHeader
        title="Operational execution"
        description={
          liveSafe
            ? "Live execution queue is gated until Laravel list contracts are restored. Fixture process/issue IDs are disabled."
            : "Fixture execution controls for layout testing only."
        }
      />
      {liveSafe ? (
        <p className="text-sm text-jp-muted" data-testid="execution-live-gated">
          Supplier process, ticket issue, and refund mark-paid actions stay disabled here until a live execution
          queue is available. Review approve/reject remains on Operations review.
        </p>
      ) : (
        <OperationalExecutionWorkspace
          cancellations={mockCancellationExecutions}
          refunds={mockRefundExecutions}
          ticketing={mockTicketingExecutions}
        />
      )}
    </div>
  );
}
