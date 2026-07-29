import { AgentPaymentsPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";
import { PublicShell } from "@/components/layout/PublicShell";

export default async function AgentPaymentsRoutePage() {
  const { session } = await requireAgentPortalAccess();

  return (
    <PublicShell session={session}>
      <AgentPaymentsPage session={session} />
    </PublicShell>
  );
}
