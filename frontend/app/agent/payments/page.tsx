import { AgentPaymentsPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentPaymentsRoutePage() {
  const { session } = await requireAgentPortalAccess();

  return (
    <AgentPaymentsPage session={session} />
  );
}
