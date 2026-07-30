import { redirect } from "next/navigation";
import Link from "next/link";
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
    <AuthShell
      eyebrow="Secure sign-in"
      headline="Verify your"
      headlineHighlight="one-time code"
      panelDescription="Enter the code sent to your registered email to complete sign-in."
      title="Verify your sign-in"
      description="Enter the one-time code sent to your email to continue."
      footer={
        <Link href="/login" className="font-semibold text-jp-primary hover:underline">
          Back to sign in
        </Link>
      }
    >
      <OtpForm />
    </AuthShell>
  );
}
