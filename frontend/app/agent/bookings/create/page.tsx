import { AgentBookingCreatePage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentBookingCreateRoutePage() {
  const { session } = await requireAgentPortalAccess();
  return <AgentBookingCreatePage session={session} />;
}
