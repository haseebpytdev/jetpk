import { PageContainer, PageHeader, Breadcrumb } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { ApiConnectionsWorkspace } from "@/features/settings/components/api-connections-workspace";

export function ApiConnectionsPageShell() {
  return (
    <PageContainer>
      <PreviewModeBadgeSlot />
      <PageHeader
        breadcrumb={
          <Breadcrumb items={[{ label: "Home" }, { label: "System" }, { label: "Integrations" }]} />
        }
        title="Integrations"
        description="Canonical technical integration management for supplier channels. Secrets are masked after save and never returned to the browser."
      />
      <DataSourceNoticeSlot />
      <ApiConnectionsWorkspace />
    </PageContainer>
  );
}
