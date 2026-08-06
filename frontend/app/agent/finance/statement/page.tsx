import { AgentFinanceStatementPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentFinanceStatementRoutePage() {
  const { session } = await requireAgentPortalAccess();
  return <AgentFinanceStatementPage session={session} />;
}
