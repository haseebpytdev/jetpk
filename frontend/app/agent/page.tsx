import { PageContainer } from "@/components/layout/PageContainer";
import { PublicShell } from "@/components/layout/PublicShell";
import { fetchSessionBootstrapFromCookies, mapBootstrapToPublicSession } from "@/features/auth/services/session-service";
import { cookies } from "next/headers";

export default async function AgentPortalPlaceholderPage() {
  const cookieStore = await cookies();
  const bootstrap = await fetchSessionBootstrapFromCookies(cookieStore.getAll());
  const session = mapBootstrapToPublicSession(bootstrap);

  return (
    <PublicShell session={session}>
      <PageContainer className="py-16">
        <div className="mx-auto max-w-2xl rounded-jp-lg border border-jp-border bg-jp-surface p-8 text-center shadow-jp-sm">
          <h1 className="text-jp-h2 font-bold text-jp-text">Agent portal</h1>
          <p className="mt-3 text-jp-sm text-jp-muted">
            The JetPakistan agent dashboard presentation will expand in a later phase. Use the existing Laravel agent
            portal for operational workflows until then.
          </p>
        </div>
      </PageContainer>
    </PublicShell>
  );
}
