import { AgentStaffCreatePage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentStaffCreateRoutePage() {
  const { session } = await requireAgentPortalAccess();
  return <AgentStaffCreatePage session={session} />;
}
