import { PublicShell } from "@/components/layout/PublicShell";
import { PageContainer } from "@/components/layout/PageContainer";
import { SecondaryButton } from "@/components/ui/SecondaryButton";
import { getPublicSession } from "@/services/session";
import Link from "next/link";

export default async function NotFoundPage() {
  const session = await getPublicSession();

  return (
    <PublicShell session={session}>
      <PageContainer className="py-jp-5xl">
        <div className="mx-auto max-w-xl rounded-jp-card border border-jp-border bg-jp-surface p-jp-3xl text-center shadow-jp-card">
          <p className="text-jp-sm font-semibold uppercase tracking-[0.18em] text-jp-primary">404</p>
          <h1 className="mt-3 font-display text-jp-h2 font-bold text-jp-text">Page not found</h1>
          <p className="mt-3 text-jp-body text-jp-muted">
            The page you are looking for is not available yet in the JetPakistan public frontend shell.
          </p>
          <div className="mt-8 flex justify-center">
            <Link href="/">
              <SecondaryButton>Back to home</SecondaryButton>
            </Link>
          </div>
        </div>
      </PageContainer>
    </PublicShell>
  );
}
