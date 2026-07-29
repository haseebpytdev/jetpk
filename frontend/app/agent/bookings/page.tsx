import { AgentBookingsPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";
import { PublicShell } from "@/components/layout/PublicShell";

export default async function AgentBookingsRoutePage() {
  const { session } = await requireAgentPortalAccess();

  return (
    <PublicShell session={session}>
      <AgentBookingsPage session={session} />
    </PublicShell>
  );
}
