import Link from "next/link";
import { AuthShell, CustomerRegistrationForm } from "@/features/auth";
import { GuestAuthRedirect } from "@/features/auth/components/GuestAuthRedirect";
import { SIGNUP_BENEFITS } from "@/features/auth/config/auth-benefits";

/**
 * Guest registration — no SSR cookies()/session await (soft-nav).
 * Signed-in visitors are redirected client-side.
 */
export default function RegisterPage() {
  return (
    <>
      <GuestAuthRedirect />
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
    </>
  );
}
