import { Suspense } from "react";
import Link from "next/link";
import { AuthShell, LoginForm } from "@/features/auth";
import { AuthAlreadySignedInRedirect } from "@/features/auth/components/AuthAlreadySignedInRedirect";
import { LoginSessionNotice } from "@/features/auth/components/LoginSessionNotice";

/** Soft-nav: static RSC shell — session redirect after hydration. */
export const revalidate = 60;

function LoginFormFallback() {
  return <div className="min-h-[12rem] animate-pulse rounded-jp-md bg-jp-surface-muted" aria-hidden="true" />;
}

export default function LoginPage() {
  return (
    <AuthShell
      title="Log in to your account"
      description="Welcome back. Enter your details to continue."
      secondaryCard={
        <div className="space-y-3 text-center">
          <p className="text-jp-sm font-semibold text-jp-text">New to JetPakistan?</p>
          <p className="text-jp-sm text-jp-muted">Create an account and start your journey with us.</p>
          <Link
            href="/register"
            className="inline-flex min-h-jp-button w-full items-center justify-center rounded-jp-md border border-jp-brand px-4 text-jp-sm font-semibold text-jp-brand hover:bg-jp-brand-soft focus-visible:shadow-jp-focus"
          >
            Sign up
          </Link>
        </div>
      }
    >
      <AuthAlreadySignedInRedirect />
      <Suspense fallback={null}>
        <LoginSessionNotice />
      </Suspense>
      <Suspense fallback={<LoginFormFallback />}>
        <LoginForm />
      </Suspense>
    </AuthShell>
  );
}
