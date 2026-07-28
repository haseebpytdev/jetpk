import { AuthShell, ForgotPasswordForm } from "@/features/auth";
import { fetchSessionBootstrapFromCookies, mapBootstrapToPublicSession } from "@/features/auth/services/session-service";
import { PublicShell } from "@/components/layout/PublicShell";
import { cookies } from "next/headers";

export default async function ForgotPasswordPage() {
  const cookieStore = await cookies();
  const bootstrap = await fetchSessionBootstrapFromCookies(cookieStore.getAll());
  const session = mapBootstrapToPublicSession(bootstrap);

  return (
    <PublicShell session={session}>
      <AuthShell title="Forgot password" description="We will send reset instructions if an account exists for your email.">
        <ForgotPasswordForm />
      </AuthShell>
    </PublicShell>
  );
}
