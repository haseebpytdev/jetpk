import { redirect } from "next/navigation";
import Link from "next/link";
import { AuthShell, CustomerRegistrationForm } from "@/features/auth";
import { SIGNUP_BENEFITS } from "@/features/auth/config/auth-benefits";
import { fetchSessionBootstrapFromCookies } from "@/features/auth/services/session-service";
import { cookies } from "next/headers";
import { sanitizeDashboardUrl } from "@/features/auth/utils/dashboard-allowlist";

export default async function RegisterPage() {
  const cookieStore = await cookies();
  const bootstrap = await fetchSessionBootstrapFromCookies(cookieStore.getAll());
  if (bootstrap.authenticated) {
    redirect(sanitizeDashboardUrl(bootstrap.dashboard_url, "/"));
  }

  return (
    <AuthShell
      eyebrow="Join JetPakistan"
      headline="Create"
      headlineHighlight="your account"
      panelDescription="Join travelers who book and manage trips with JetPakistan."
      benefits={SIGNUP_BENEFITS}
      title="Sign up"
      description="Fill in your details to get started."
      footer={
        <span>
          Already have an account?{" "}
          <Link href="/login" className="font-semibold text-jp-primary hover:underline">
            Log in
          </Link>
        </span>
      }
    >
      <CustomerRegistrationForm />
    </AuthShell>
  );
}
