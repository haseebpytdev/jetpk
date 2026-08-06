import { AgentTravelersPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentTravelersRoutePage() {
  const { session } = await requireAgentPortalAccess();
  return <AgentTravelersPage session={session} />;
}
