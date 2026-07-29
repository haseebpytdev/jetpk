import { AuthShell, ForgotPasswordForm } from "@/features/auth";

export default async function ForgotPasswordPage() {
  return (
    <AuthShell title="Forgot password" description="We will send reset instructions if an account exists for your email.">
      <ForgotPasswordForm />
    </AuthShell>
  );
}
