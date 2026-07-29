import { AgentInvoicesPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentInvoicesRoutePage() {
  const { session } = await requireAgentPortalAccess();

  return (
    <AgentInvoicesPage session={session} />
  );
}
