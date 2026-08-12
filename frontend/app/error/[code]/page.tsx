import { PageContainer } from "@/components/layout/PageContainer";
import { PublicShell } from "@/components/layout/PublicShell";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { SecondaryButton } from "@/components/ui/SecondaryButton";
import { getPublicSession } from "@/services/session";
import Link from "next/link";
import { notFound } from "next/navigation";

const ERROR_COPY: Record<string, { title: string; message: string }> = {
  "403": {
    title: "Access denied",
    message: "You do not have permission to view this page.",
  },
  "419": {
    title: "Session expired",
    message: "Your session expired. Refresh and try again.",
  },
  "429": {
    title: "Too many requests",
    message: "Please wait a moment and try again.",
  },
  "500": {
    title: "Something went wrong",
    message: "We could not complete this request right now. Please try again shortly.",
  },
  "503": {
    title: "Service unavailable",
    message: "JetPakistan is temporarily unavailable. Please try again shortly.",
  },
};

type ErrorPageProps = {
  params: Promise<{ code: string }>;
};

export default async function PublicErrorCodePage({ params }: ErrorPageProps) {
  const { code } = await params;
  const copy = ERROR_COPY[code];
  if (!copy) {
    notFound();
  }

  const session = await getPublicSession();

  return (
    <PublicShell session={session}>
      <PageContainer className="py-jp-5xl">
        <div className="mx-auto max-w-2xl rounded-jp-card border border-jp-border bg-jp-surface p-jp-3xl text-center shadow-jp-card">
          <p className="text-jp-sm font-semibold uppercase tracking-[0.18em] text-jp-primary">{code}</p>
          <h1 className="mt-3 font-sans text-jp-h2 font-bold text-jp-text">{copy.title}</h1>
          <p className="mt-3 text-jp-body text-jp-muted">{copy.message}</p>
          <div className="mt-8 flex flex-wrap justify-center gap-3">
            <Link href="/">
              <PrimaryButton>Home</PrimaryButton>
            </Link>
            <Link href="/login">
              <SecondaryButton>Sign in</SecondaryButton>
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
