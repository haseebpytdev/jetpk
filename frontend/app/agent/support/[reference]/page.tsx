import { SupportCaseDetailPage } from "@/features/agent-dashboard";
import { requireAgentPortalAccess } from "@/features/auth/server/agent-portal-access";

type PageProps = {
  params: Promise<{ reference: string }>;
};

export default async function AgentSupportDetailRoutePage({ params }: PageProps) {
  const { session } = await requireAgentPortalAccess();
  const { reference } = await params;

  return <SupportCaseDetailPage session={session} reference={reference} />;
}
