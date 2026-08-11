import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { EmptyState } from "@/components/ui/empty-state";

export const metadata = { title: "Agent applications — JetPakistan Dashboard" };

export default function AgentApplicationsPage() {
  return (
    <PageContainer>
      <PageHeader
        title="Agent applications"
        description="Review and process agent onboarding requests from the Next operator shell."
      />
      <EmptyState
        title="Application review"
        description="Approve/reject intake remains Laravel-backed. This surface replaces the Blade handoff."
      />
    </PageContainer>
  );
}
