import Link from "next/link";
import { AuthShell, ForgotPasswordForm } from "@/features/auth";
import { RECOVERY_BENEFITS } from "@/features/auth/config/auth-benefits";

export default async function ForgotPasswordPage() {
  return (
    <AuthShell
      eyebrow="Account recovery"
      headline="Reset your"
      headlineHighlight="password"
      panelDescription="Enter the email associated with your account. We will send reset instructions when applicable."
      benefits={RECOVERY_BENEFITS}
      title="Forgot password"
      description="We use the same response whether or not an account exists for your email."
      footer={
        <Link href="/login" className="font-semibold text-jp-primary hover:underline">
          Return to sign in
        </Link>
      }
    >
      <ForgotPasswordForm />
    </AuthShell>
  );
}
