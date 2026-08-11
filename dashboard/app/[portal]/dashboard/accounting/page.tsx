import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { EmptyState } from "@/components/ui/empty-state";

export const metadata = { title: "Accounting — JetPakistan Dashboard" };

export default function AccountingPage() {
  return (
    <PageContainer>
      <PageHeader title="Accounting" description="Accounting and ledger operator workspace." />
      <EmptyState
        title="Accounting ledger"
        description="Next presentation for accounting surfaces. Ledger services remain Laravel."
      />
    </PageContainer>
  );
}
