import { PageContainer } from "@/components/layout/PageContainer";
import { PublicShell } from "@/components/layout/PublicShell";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { SecondaryButton } from "@/components/ui/SecondaryButton";
import { getPublicSession } from "@/services/session";
import Link from "next/link";

export default async function NotFoundPage() {
  const session = await getPublicSession();

  return (
    <PublicShell session={session}>
      <PageContainer className="py-jp-5xl">
        <div className="mx-auto max-w-2xl rounded-jp-card border border-jp-border bg-jp-surface p-jp-3xl text-center shadow-jp-card">
          <p className="text-jp-sm font-semibold uppercase tracking-[0.18em] text-jp-primary">404</p>
          <h1 className="mt-3 font-sans text-jp-h2 font-bold text-jp-text">Page not found</h1>
          <p className="mt-3 text-jp-body text-jp-muted">
            The page you requested is not available. Check the URL or use the links below to continue.
          </p>
          <div className="mt-8 flex flex-wrap justify-center gap-3">
            <Link href="/">
              <PrimaryButton>Home</PrimaryButton>
            </Link>
            <Link href="/#main-content">
              <SecondaryButton>Search flights</SecondaryButton>
            </Link>
            <Link href="/support">
              <SecondaryButton>Support</SecondaryButton>
            </Link>
          </div>
        </div>
      </PageContainer>
    </PublicShell>
  );
}
