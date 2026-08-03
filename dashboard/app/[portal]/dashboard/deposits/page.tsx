import { DepositsWorkspace } from "@/features/deposits/deposits-workspace";
import { PageHeader } from "@/components/ui/page-layout";
import { getDepositsPage } from "@/services/deposit-service";

export default async function DepositsPage() {
  const result = await getDepositsPage();

  return (
    <div className="space-y-6">
      <PageHeader title="Agent deposits" description="Review pending agent deposit proofs and wallet postings." />
      <DepositsWorkspace deposits={result.deposits} />
    </div>
  );
}
