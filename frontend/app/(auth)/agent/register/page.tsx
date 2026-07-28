import { AuthShell, AgentRegistrationForm } from "@/features/auth";
import { fetchSessionBootstrapFromCookies, mapBootstrapToPublicSession } from "@/features/auth/services/session-service";
import { PublicShell } from "@/components/layout/PublicShell";
import { cookies } from "next/headers";

export default async function AgentRegisterPage() {
  const cookieStore = await cookies();
  const bootstrap = await fetchSessionBootstrapFromCookies(cookieStore.getAll());
  const session = mapBootstrapToPublicSession(bootstrap);

  return (
    <PublicShell session={session}>
      <AuthShell
        title="Apply as a JetPakistan Agent"
        description="Submit your agency application for review. Approved agents receive portal access after manual review."
      >
        <AgentRegistrationForm />
      </AuthShell>
    </PublicShell>
  );
}
