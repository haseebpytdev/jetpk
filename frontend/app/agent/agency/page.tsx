import { AgentAgencyPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentAgencyRoutePage() {
  const { session } = await requireAgentPortalAccess();
  return <AgentAgencyPage session={session} />;
}
