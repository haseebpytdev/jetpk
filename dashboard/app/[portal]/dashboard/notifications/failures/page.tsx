import { Breadcrumb, PageContainer, PageHeader } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { NotificationFailuresWorkspace } from "@/features/notifications/notification-failures-workspace";

export const metadata = { title: "Failed notifications — JetPakistan Dashboard" };

export default function NotificationFailuresPage() {
  return (
    <PageContainer>
      <PreviewModeBadgeSlot />
      <PageHeader
        breadcrumb={
          <Breadcrumb
            items={[
              { label: "Home" },
              { label: "Insights & system" },
              { label: "Failed notifications" },
            ]}
          />
        }
        title="Failed notifications"
        description="Review communication delivery failures with masked recipients. Classification only — no blind retry or audit deletion."
      />
      <DataSourceNoticeSlot />
      <NotificationFailuresWorkspace />
    </PageContainer>
  );
}
