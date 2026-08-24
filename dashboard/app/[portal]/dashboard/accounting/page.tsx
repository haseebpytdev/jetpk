import { PageContainer, PageHeader, Breadcrumb } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { AccountingWorkspace } from "@/features/finance/accounting-workspace";

export const metadata = { title: "Accounting — JetPakistan Dashboard" };

export default function AccountingPage() {
  return (
    <PageContainer>
      <PreviewModeBadgeSlot />
      <PageHeader
        breadcrumb={<Breadcrumb items={[{ label: "Home" }, { label: "Finance" }, { label: "Accounting" }]} />}
        title="Accounting"
        description="Audited manual wallet credit, debit, adjustment, and reversal through Laravel FinanceAdjustmentController."
      />
      <DataSourceNoticeSlot />
      <AccountingWorkspace />
    </PageContainer>
  );
}
