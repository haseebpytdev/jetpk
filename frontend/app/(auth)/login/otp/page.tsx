import { redirect } from "next/navigation";
import { AuthShell, OtpForm } from "@/features/auth";
import { fetchSessionBootstrapFromCookies } from "@/features/auth/services/session-service";
import { cookies } from "next/headers";
import { sanitizeDashboardUrl } from "@/features/auth/utils/dashboard-allowlist";

export default async function LoginOtpPage() {
  const cookieStore = await cookies();
  const bootstrap = await fetchSessionBootstrapFromCookies(cookieStore.getAll());

  if (bootstrap.authenticated) {
    redirect(sanitizeDashboardUrl(bootstrap.dashboard_url, "/"));
  }

  if (!bootstrap.requires_otp) {
    redirect("/login");
  }

  return (
    <AuthShell title="Verify your sign-in" description="Enter the one-time code sent to your email to continue.">
      <OtpForm />
    </AuthShell>
  );
}
