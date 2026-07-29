import { DepositListPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";
import { PublicShell } from "@/components/layout/PublicShell";

export default async function AgentDepositsRoutePage() {
  const { session } = await requireAgentPortalAccess();

  return (
    <PublicShell session={session}>
      <DepositListPage session={session} />
    </PublicShell>
  );
}
