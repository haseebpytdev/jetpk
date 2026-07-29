import { WalletLedgerPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentWalletLedgerRoutePage() {
  const { session } = await requireAgentPortalAccess();

  return (
    <WalletLedgerPage session={session} />
  );
}
