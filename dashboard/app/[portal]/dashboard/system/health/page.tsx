import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { EmptyState } from "@/components/ui/empty-state";

export const metadata = { title: "System health — JetPakistan Dashboard" };

export default function SystemHealthPage() {
  return (
    <PageContainer>
      <PageHeader
        title="System health"
        description="Operational health and deployment readiness from the Next back office."
      />
      <EmptyState
        title="Health workspace"
        description="Sanitized health signals are owned by Next presentation with Laravel service backends."
      />
    </PageContainer>
  );
}
