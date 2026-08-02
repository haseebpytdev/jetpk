import { redirect } from "next/navigation";
import Link from "next/link";
import { AuthShell, LoginForm } from "@/features/auth";
import { LoginSessionNotice } from "@/features/auth/components/LoginSessionNotice";
import { fetchSessionBootstrapFromCookies } from "@/features/auth/services/session-service";
import { cookies } from "next/headers";
import { sanitizeDashboardUrl } from "@/features/auth/utils/dashboard-allowlist";

type LoginPageProps = {
  searchParams: Promise<{ reason?: string }>;
};

export default async function LoginPage({ searchParams }: LoginPageProps) {
  const { reason } = await searchParams;
  const cookieStore = await cookies();
  const bootstrap = await fetchSessionBootstrapFromCookies(cookieStore.getAll());
  if (bootstrap.authenticated) {
    redirect(sanitizeDashboardUrl(bootstrap.landing_route ?? bootstrap.dashboard_url, "/"));
  }

  return (
    <AuthShell
      title="Log in to your account"
      description="Welcome back. Enter your details to continue."
      secondaryCard={
        <div className="space-y-3 text-center">
          <p className="text-jp-sm font-semibold text-jp-text">New to JetPakistan?</p>
          <p className="text-jp-sm text-jp-muted">Create an account and start your journey with us.</p>
          <Link
            href="/register"
            className="inline-flex min-h-jp-button w-full items-center justify-center rounded-jp-md border border-jp-brand px-4 text-jp-sm font-semibold text-jp-brand hover:bg-jp-brand-soft focus-visible:shadow-jp-focus"
          >
            Sign up
          </Link>
        </div>
      }
    >
      <LoginSessionNotice reason={reason} />
      <LoginForm />
    </AuthShell>
  );
}
