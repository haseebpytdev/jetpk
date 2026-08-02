import { PageContainer } from "@/components/layout/PageContainer";
import { PublicShell } from "@/components/layout/PublicShell";
import { fetchSessionBootstrapFromCookies, mapBootstrapToPublicSession } from "@/features/auth/services/session-service";
import { cookies } from "next/headers";
import Link from "next/link";

type AccessDeniedPageProps = {
  searchParams: Promise<{ reason?: string }>;
};

export default async function AccessDeniedPage({ searchParams }: AccessDeniedPageProps) {
  const { reason } = await searchParams;
  const cookieStore = await cookies();
  const bootstrap = await fetchSessionBootstrapFromCookies(cookieStore.getAll());
  const session = mapBootstrapToPublicSession(bootstrap);

  const message =
    reason === "account-disabled"
      ? "Your account is not active. Contact support if you need assistance."
      : "You do not have permission to view this page. Contact support if you believe this is an error.";

  return (
    <PublicShell session={session}>
      <PageContainer className="py-16">
        <div className="mx-auto max-w-xl rounded-jp-lg border border-jp-border bg-jp-surface p-8 text-center shadow-jp-sm">
          <h1 className="text-jp-h2 font-bold text-jp-text">Access denied</h1>
          <p className="mt-3 text-jp-sm text-jp-muted">{message}</p>
          <Link href="/" className="mt-6 inline-flex text-jp-sm font-semibold text-jp-primary hover:underline">
            Return home
          </Link>
        </div>
      </PageContainer>
    </PublicShell>
  );
}
