import { AgentTravelerFormPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

export default async function AgentTravelerEditRoutePage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { session } = await requireAgentPortalAccess();
  const { id } = await params;
  return <AgentTravelerFormPage session={session} travelerId={Number(id)} />;
}
