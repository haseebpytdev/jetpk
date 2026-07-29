import { AgentNotificationsPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentNotificationsRoutePage() {
  const { session } = await requireAgentPortalAccess();

  return (
    <AgentNotificationsPage session={session} />
  );
}
