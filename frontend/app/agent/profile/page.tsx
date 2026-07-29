import { AgentProfilePage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentProfileRoutePage() {
  const { session } = await requireAgentPortalAccess();

  return (
    <AgentProfilePage session={session} />
  );
}
