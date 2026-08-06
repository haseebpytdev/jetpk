import { AgentAccountingLedgerPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentAccountingLedgerRoutePage() {
  const { session } = await requireAgentPortalAccess();
  return <AgentAccountingLedgerPage session={session} />;
}
