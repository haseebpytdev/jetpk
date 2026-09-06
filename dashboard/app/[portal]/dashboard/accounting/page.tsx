import { PageContainer, PageHeader, Breadcrumb } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { AccountingWorkspace } from "@/features/finance/accounting-workspace";

export const metadata = { title: "Wallet Adjustments — JetPakistan Dashboard" };

export default function AccountingPage() {
  return (
    <PageContainer>
      <PreviewModeBadgeSlot />
      <PageHeader
        breadcrumb={<Breadcrumb items={[{ label: "Home" }, { label: "Finance" }, { label: "Wallets" }, { label: "Manual Adjustments" }]} />}
        title="Wallet Adjustments"
        description="Post audited manual credits, debits, and reversals on an agency wallet. Every change is confirmed, recorded, and cannot be edited after it is posted."
      />
      <DataSourceNoticeSlot />
      <AccountingWorkspace />
    </PageContainer>
  );
}
