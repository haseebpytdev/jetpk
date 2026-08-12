import { DepositsWorkspace } from "@/features/deposits/deposits-workspace";
import { PageHeader } from "@/components/ui/page-layout";
import { getDepositsPage } from "@/services/deposit-service";

export default async function DepositsPage() {
  const result = await getDepositsPage();

  return (
    <div className="space-y-6">
      <PageHeader
        title="Agent deposits"
        description="Review deposit requests and proofs. Production Owner-UAT does not approve deposits or post manual wallet credits — use local/test fixtures for mutation proof. Immutable ledger adjustments already exist in Laravel FinanceAdjustmentController / ManualWalletAdjustmentService."
      />
      <DepositsWorkspace deposits={result.deposits} />
    </div>
  );
}
