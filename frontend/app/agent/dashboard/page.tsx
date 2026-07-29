import { AgentOverviewPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentDashboardRoutePage() {
  const { session } = await requireAgentPortalAccess();

  return (
    <AgentOverviewPage session={session} />
  );
}
