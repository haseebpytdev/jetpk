import { AuthShell, ResetPasswordForm } from "@/features/auth";
import { fetchSessionBootstrapFromCookies, mapBootstrapToPublicSession } from "@/features/auth/services/session-service";
import { PublicShell } from "@/components/layout/PublicShell";
import { cookies } from "next/headers";

type ResetPasswordPageProps = {
  params: Promise<{ token: string }>;
  searchParams: Promise<{ email?: string }>;
};

export default async function ResetPasswordPage({ params, searchParams }: ResetPasswordPageProps) {
  const { token } = await params;
  const { email } = await searchParams;
  const cookieStore = await cookies();
  const bootstrap = await fetchSessionBootstrapFromCookies(cookieStore.getAll());
  const session = mapBootstrapToPublicSession(bootstrap);

  return (
    <PublicShell session={session}>
      <AuthShell title="Reset your password" description="Choose a new password for your JetPakistan account.">
        <ResetPasswordForm token={token} email={email} />
      </AuthShell>
    </PublicShell>
  );
}
