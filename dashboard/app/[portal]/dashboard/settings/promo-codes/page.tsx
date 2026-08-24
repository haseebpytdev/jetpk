import { PageContainer, PageHeader, Breadcrumb } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { PromoCodesWorkspace } from "@/features/settings/components/promo-codes-workspace";

export const metadata = { title: "Promo codes — JetPakistan Dashboard" };

export default function PromoCodesSettingsPage() {
  return (
    <PageContainer>
      <PreviewModeBadgeSlot />
      <PageHeader
        breadcrumb={
          <Breadcrumb items={[{ label: "Home" }, { label: "Insights & system" }, { label: "Settings" }, { label: "Promo codes" }]} />
        }
        title="Promo codes"
        description="Create, list, and toggle promo codes through PromoCodeController."
      />
      <DataSourceNoticeSlot />
      <PromoCodesWorkspace />
    </PageContainer>
  );
}
