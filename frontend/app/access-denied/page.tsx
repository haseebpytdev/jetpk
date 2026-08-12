import { PageContainer } from "@/components/layout/PageContainer";
import { PublicShell } from "@/components/layout/PublicShell";
import { fetchSessionBootstrapFromCookies, mapBootstrapToPublicSession } from "@/features/auth/services/session-service";
import { cookies } from "next/headers";
import Link from "next/link";

type AccessDeniedPageProps = {
  searchParams: Promise<{ reason?: string }>;
};

function copyForReason(reason?: string): { title: string; message: string; code?: string } {
  switch (reason) {
    case "not-found":
      return {
        code: "404",
        title: "Page not found",
        message: "The page you requested is not available. Check the URL or return home to continue.",
      };
    case "forbidden":
    case "account-disabled":
      return {
        code: "403",
        title: "Access denied",
        message:
          reason === "account-disabled"
            ? "Your account is not active. Contact support if you need assistance."
            : "You do not have permission to view this page. Contact support if you believe this is an error.",
      };
    case "rate-limited":
      return {
        code: "429",
        title: "Too many requests",
        message: "Please wait a moment and try again.",
      };
    case "unavailable":
      return {
        code: "503",
        title: "Service unavailable",
        message: "JetPakistan is temporarily unavailable. Please try again shortly.",
      };
    case "service-error":
      return {
        code: "500",
        title: "Something went wrong",
        message: "We could not complete this request right now. Please try again shortly.",
      };
    default:
      return {
        title: "Access denied",
        message: "You do not have permission to view this page. Contact support if you believe this is an error.",
      };
  }
}

export default async function AccessDeniedPage({ searchParams }: AccessDeniedPageProps) {
  const { reason } = await searchParams;
  const cookieStore = await cookies();
  const bootstrap = await fetchSessionBootstrapFromCookies(cookieStore.getAll());
  const session = mapBootstrapToPublicSession(bootstrap);
  const copy = copyForReason(reason);

  return (
    <PublicShell session={session}>
      <PageContainer className="py-16">
        <div className="mx-auto max-w-xl rounded-jp-lg border border-jp-border bg-jp-surface p-8 text-center shadow-jp-sm">
          {copy.code ? (
            <p className="text-jp-sm font-semibold uppercase tracking-[0.18em] text-jp-primary">{copy.code}</p>
          ) : null}
          <h1 className="mt-3 font-display text-jp-h2 font-bold text-jp-text">{copy.title}</h1>
          <p className="mt-3 text-jp-sm text-jp-muted">{copy.message}</p>
          <div className="mt-6 flex flex-wrap justify-center gap-4">
            <Link href="/" className="text-jp-sm font-semibold text-jp-primary hover:underline">
              Return home
            </Link>
            <Link href="/support" className="text-jp-sm font-semibold text-jp-primary hover:underline">
              Support
            </Link>
          </div>
        </div>
      </PageContainer>
    </PublicShell>
  );
}
