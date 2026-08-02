import { AgentReportsPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentReportsRoutePage() {
  const { session } = await requireAgentPortalAccess();
  return <AgentReportsPage session={session} />;
}
