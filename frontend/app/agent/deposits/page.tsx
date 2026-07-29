import { DepositListPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentDepositsRoutePage() {
  const { session } = await requireAgentPortalAccess();

  return (
    <DepositListPage session={session} />
  );
}
