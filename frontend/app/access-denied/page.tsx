import { PageContainer } from "@/components/layout/PageContainer";
import { PublicShell } from "@/components/layout/PublicShell";
import { fetchSessionBootstrapFromCookies, mapBootstrapToPublicSession } from "@/features/auth/services/session-service";
import { cookies } from "next/headers";
import Link from "next/link";

export default async function AccessDeniedPage() {
  const cookieStore = await cookies();
  const bootstrap = await fetchSessionBootstrapFromCookies(cookieStore.getAll());
  const session = mapBootstrapToPublicSession(bootstrap);

  return (
    <PublicShell session={session}>
      <PageContainer className="py-16">
        <div className="mx-auto max-w-xl rounded-jp-lg border border-jp-border bg-jp-surface p-8 text-center shadow-jp-sm">
          <h1 className="text-jp-h2 font-bold text-jp-text">Access denied</h1>
          <p className="mt-3 text-jp-sm text-jp-muted">
            You do not have permission to view this page. Contact support if you believe this is an error.
          </p>
          <Link href="/" className="mt-6 inline-flex text-jp-sm font-semibold text-jp-primary hover:underline">
            Return home
          </Link>
        </div>
      </PageContainer>
    </PublicShell>
  );
}
