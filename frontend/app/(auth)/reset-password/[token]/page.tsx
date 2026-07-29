import { AuthShell, ResetPasswordForm } from "@/features/auth";
import { fetchSessionBootstrapFromCookies } from "@/features/auth/services/session-service";
import { cookies } from "next/headers";

type ResetPasswordPageProps = {
  params: Promise<{ token: string }>;
  searchParams: Promise<{ email?: string }>;
};

export default async function ResetPasswordPage({ params, searchParams }: ResetPasswordPageProps) {
  const { token } = await params;
  const { email } = await searchParams;
  const cookieStore = await cookies();
  await fetchSessionBootstrapFromCookies(cookieStore.getAll());

  return (
    <AuthShell title="Reset your password" description="Choose a new password for your JetPakistan account.">
      <ResetPasswordForm token={token} email={email} />
    </AuthShell>
  );
}
