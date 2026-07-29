import { NewDepositPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentNewDepositRoutePage() {
  const { session } = await requireAgentPortalAccess();

  return (
    <NewDepositPage session={session} />
  );
}
