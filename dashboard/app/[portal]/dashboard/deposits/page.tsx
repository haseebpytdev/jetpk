import { DepositsWorkspace } from "@/features/deposits/deposits-workspace";
import { PageHeader } from "@/components/ui/page-layout";
import { getDepositsPage } from "@/services/deposit-service";

export default async function DepositsPage() {
  const result = await getDepositsPage();

  return (
    <div className="space-y-6">
      <PageHeader
        title="Agent deposits"
        description="Review deposit requests, proofs, and approve or reject through the authoritative Laravel wallet service."
      />
      <DepositsWorkspace deposits={result.deposits} />
    </div>
  );
}
