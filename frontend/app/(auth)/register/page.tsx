import { redirect } from "next/navigation";
import { AuthShell, CustomerRegistrationForm } from "@/features/auth";
import { fetchSessionBootstrapFromCookies, mapBootstrapToPublicSession } from "@/features/auth/services/session-service";
import { PublicShell } from "@/components/layout/PublicShell";
import { cookies } from "next/headers";
import { sanitizeDashboardUrl } from "@/features/auth/utils/dashboard-allowlist";

export default async function RegisterPage() {
  const cookieStore = await cookies();
  const bootstrap = await fetchSessionBootstrapFromCookies(cookieStore.getAll());
  if (bootstrap.authenticated) {
    redirect(sanitizeDashboardUrl(bootstrap.dashboard_url, "/"));
  }

  const session = mapBootstrapToPublicSession(bootstrap);

  return (
    <PublicShell session={session}>
      <AuthShell title="Create your account" description="Register as a JetPakistan customer to book and manage trips.">
        <CustomerRegistrationForm />
      </AuthShell>
    </PublicShell>
  );
}
