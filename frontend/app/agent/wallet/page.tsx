import { WalletOverviewPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentWalletRoutePage() {
  const { session } = await requireAgentPortalAccess();

  return (
    <WalletOverviewPage session={session} />
  );
}
