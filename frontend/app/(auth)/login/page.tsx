import { redirect } from "next/navigation";
import { AuthShell, LoginForm } from "@/features/auth";
import { fetchSessionBootstrapFromCookies } from "@/features/auth/services/session-service";
import { cookies } from "next/headers";
import { sanitizeDashboardUrl } from "@/features/auth/utils/dashboard-allowlist";

export default async function LoginPage() {
  const cookieStore = await cookies();
  const bootstrap = await fetchSessionBootstrapFromCookies(cookieStore.getAll());
  if (bootstrap.authenticated) {
    redirect(sanitizeDashboardUrl(bootstrap.dashboard_url, "/"));
  }

  return (
    <AuthShell title="Sign in" description="Access your JetPakistan account to manage bookings and travel.">
      <LoginForm />
    </AuthShell>
  );
}
