import { AgentTravelerFormPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentTravelerCreateRoutePage() {
  const { session } = await requireAgentPortalAccess();
  return <AgentTravelerFormPage session={session} />;
}
