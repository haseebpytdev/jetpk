import { AgentStaffDetailPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentStaffDetailRoutePage({ params }: { params: Promise<{ id: string }> }) {
  const { session } = await requireAgentPortalAccess();
  const { id } = await params;
  return <AgentStaffDetailPage session={session} staffId={Number(id)} />;
}
