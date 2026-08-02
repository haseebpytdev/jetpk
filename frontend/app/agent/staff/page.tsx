import { AgentStaffPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentStaffRoutePage() {
  const { session } = await requireAgentPortalAccess();
  return <AgentStaffPage session={session} />;
}
