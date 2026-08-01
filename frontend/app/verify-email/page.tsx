import Link from "next/link";
import { AuthShell } from "@/features/auth";

type VerifyEmailPageProps = {
  searchParams: Promise<{
    status?: string;
    verified?: string;
    already?: string;
    expired?: string;
    invalid?: string;
  }>;
};

function resolveVerifyState(params: Awaited<VerifyEmailPageProps["searchParams"]>) {
  if (params.verified === "1" || params.status === "verified") {
    return {
      title: "Email verified",
      description: "Your email address has been verified. You can now sign in and continue booking.",
      tone: "success" as const,
    };
  }
  if (params.already === "1" || params.status === "already-verified") {
    return {
      title: "Already verified",
      description: "This email address is already verified. Sign in to access your account.",
      tone: "info" as const,
    };
  }
  if (params.expired === "1" || params.status === "expired") {
    return {
      title: "Verification link expired",
      description: "This verification link has expired. Sign in to request a new verification email.",
      tone: "warning" as const,
    };
  }
  if (params.invalid === "1" || params.status === "invalid") {
    return {
      title: "Invalid verification link",
      description: "We could not verify this link. Sign in to request a new verification email.",
      tone: "warning" as const,
    };
  }
  return {
    title: "Verify your email",
    description:
      "We sent a verification link to your email address. Open the link to secure your account, then return here to sign in.",
    tone: "info" as const,
  };
}

export const metadata = {
  title: "Verify Email",
  robots: { index: false, follow: false },
};

export default async function VerifyEmailPage({ searchParams }: VerifyEmailPageProps) {
  const params = await searchParams;
  const state = resolveVerifyState(params);

  return (
    <AuthShell
      title={state.title}
      description={state.description}
      headline="Verify your"
      headlineHighlight="email"
      panelDescription="Confirm your email address to secure your JetPakistan account."
      footer={
        <div className="flex flex-col gap-jp-sm text-center">
          <Link
            href="/login"
            className="inline-flex min-h-jp-button items-center justify-center rounded-jp-md bg-jp-brand px-4 text-jp-sm font-semibold text-white hover:bg-jp-brand-hover focus-visible:shadow-jp-focus"
          >
            Continue to sign in
          </Link>
          <Link href="/support" className="text-jp-sm font-medium text-jp-brand hover:underline">
            Need help?
          </Link>
        </div>
      }
    >
      <div
        className="rounded-jp-md border border-jp-border bg-jp-surface-muted p-jp-lg text-jp-sm text-jp-muted"
        role="status"
        data-verify-tone={state.tone}
      >
        {state.tone === "success" ? (
          <p>Your account is ready. Sign in to manage bookings and traveler details.</p>
        ) : state.tone === "warning" ? (
          <p>If you continue to have trouble, contact support with the email address on your account.</p>
        ) : (
          <p>Check your inbox and spam folder for a message from JetPakistan. Links expire for your security.</p>
        )}
      </div>
    </AuthShell>
  );
}
