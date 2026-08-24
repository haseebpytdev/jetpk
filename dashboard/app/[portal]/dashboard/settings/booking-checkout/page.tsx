import { PageContainer, PageHeader, Breadcrumb } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { BookingCheckoutWorkspace } from "@/features/settings/components/booking-checkout-workspace";

export const metadata = { title: "Booking & Checkout — JetPakistan Dashboard" };

export default function BookingCheckoutSettingsPage() {
  return (
    <PageContainer>
      <PreviewModeBadgeSlot />
      <PageHeader
        breadcrumb={
          <Breadcrumb
            items={[
              { label: "Home" },
              { label: "Insights & system" },
              { label: "Settings" },
              { label: "Booking & Checkout" },
            ]}
          />
        }
        title="Booking & Checkout"
        description="Control guest checkout and card payment availability for the public booking flow."
      />
      <DataSourceNoticeSlot />
      <BookingCheckoutWorkspace />
    </PageContainer>
  );
}
