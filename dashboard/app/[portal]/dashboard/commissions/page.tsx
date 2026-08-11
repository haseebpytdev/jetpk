import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { EmptyState } from "@/components/ui/empty-state";

export const metadata = { title: "Commissions — JetPakistan Dashboard" };

export default function CommissionsPage() {
  return (
    <PageContainer>
      <PageHeader
        title="Commissions"
        description="Agent commission ledger workspace backed by Laravel domain services."
      />
      <EmptyState
        title="Commission ledger"
        description="Next owns presentation. Payout mutations remain Laravel-authorized and AD-009 constrained."
      />
    </PageContainer>
  );
}
