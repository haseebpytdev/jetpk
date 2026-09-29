import { Breadcrumb, PageContainer, PageHeader } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { ProfilePageContent } from "@/features/profile/profile-page-content";

export const metadata = { title: "My Profile — JetPakistan Dashboard" };

export default function ProfilePage() {
  return (
    <PageContainer>
      <PreviewModeBadgeSlot />
      <PageHeader
        breadcrumb={<Breadcrumb items={[{ label: "Home" }, { label: "My Profile" }]} />}
        title="My Profile"
        description="View and update your JetPakistan account contact details. Security and role changes use authorized workflows."
      />
      <DataSourceNoticeSlot />
      <ProfilePageContent />
    </PageContainer>
  );
}
