import { AgentSecurityPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentSecurityRoutePage() {
  const { session } = await requireAgentPortalAccess();

  return (
    <AgentSecurityPage session={session} />
  );
}
