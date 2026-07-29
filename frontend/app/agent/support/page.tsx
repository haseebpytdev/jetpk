import { AgentSupportPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentSupportRoutePage() {
  const { session } = await requireAgentPortalAccess();

  return (
    <AgentSupportPage session={session} />
  );
}
