import { AgentCommissionsPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentCommissionsRoutePage() {
  const { session } = await requireAgentPortalAccess();
  return <AgentCommissionsPage session={session} />;
}
