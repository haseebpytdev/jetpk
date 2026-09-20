import Link from "next/link";
import { AuthShell, CustomerRegistrationForm } from "@/features/auth";
import { AuthAlreadySignedInRedirect } from "@/features/auth/components/AuthAlreadySignedInRedirect";
import { SIGNUP_BENEFITS } from "@/features/auth/config/auth-benefits";

/** Soft-nav: avoid cookies()/force-dynamic so /register RSC stays prefetchable. */
export const revalidate = 60;

export default function RegisterPage() {
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
      <AuthAlreadySignedInRedirect />
      <CustomerRegistrationForm />
    </AuthShell>
  );
}
