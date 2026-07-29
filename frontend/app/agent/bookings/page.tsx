import { AgentBookingsPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentBookingsRoutePage() {
  const { session } = await requireAgentPortalAccess();

  return (
    <AgentBookingsPage session={session} />
  );
}
