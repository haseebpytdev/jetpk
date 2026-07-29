import { AgentBookingDetailsPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";
import { PublicShell } from "@/components/layout/PublicShell";

type PageProps = {
  params: Promise<{ reference: string }>;
};

export default async function AgentBookingDetailRoutePage({ params }: PageProps) {
  const { session } = await requireAgentPortalAccess();
  const { reference } = await params;

  return (
    <PublicShell session={session}>
      <AgentBookingDetailsPage session={session} reference={reference} />
    </PublicShell>
  );
}
