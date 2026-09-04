"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense, useMemo } from "react";
import { AuthShell, LoginForm } from "@/features/auth";
import { GuestAuthRedirect } from "@/features/auth/components/GuestAuthRedirect";
import { LoginSessionNotice } from "@/features/auth/components/LoginSessionNotice";
import { BookingProgress } from "@/features/booking-progress";
import { sanitizeCheckoutReturnUrl } from "@/features/auth/utils/checkout-return-allowlist";

function LoginPageInner() {
  const searchParams = useSearchParams();
  const reason = searchParams.get("reason") ?? undefined;
  const redirectParam = searchParams.get("redirect") ?? undefined;
  const checkoutReturn = searchParams.get("checkout_return") ?? undefined;
  const bookingGate = searchParams.get("booking_gate") ?? undefined;
  const returnPath = useMemo(
    () => sanitizeCheckoutReturnUrl(checkoutReturn || redirectParam, ""),
    [checkoutReturn, redirectParam],
  );
  const isBookingAccountGate = bookingGate === "account";
  // Ordinary login always offers register; booking-gate commerce check is SSR-free fail-open.
  const canRegister = true;
  const registerHref = returnPath
    ? `/register?redirect=${encodeURIComponent(returnPath)}${isBookingAccountGate ? "&booking_gate=account" : ""}`
    : "/register";

  const accountProgress = [
    { key: "account", label: "Account", state: "current" as const },
    { key: "passenger_details", label: "Travelers", state: "upcoming" as const },
    { key: "review", label: "Review", state: "upcoming" as const },
    { key: "payment", label: "Payment", state: "upcoming" as const },
    { key: "confirmation", label: "Confirmation", state: "upcoming" as const },
  ];

  return (
    <>
      <GuestAuthRedirect returnPath={returnPath || undefined} />
      <AuthShell
        title={isBookingAccountGate ? "Account required to continue booking" : "Log in to your account"}
        description={
          isBookingAccountGate
            ? "Guest checkout is currently unavailable. Sign in to resume Traveler Details with your selected flight."
            : "Welcome back. Enter your details to continue."
        }
        secondaryCard={
          canRegister ? (
            <div className="space-y-3 text-center" data-testid="login-register-card">
              <p className="text-jp-sm font-semibold text-jp-text">New to JetPakistan?</p>
              <p className="text-jp-sm text-jp-muted">Create an account and start your journey with us.</p>
              <Link
                href={registerHref}
                className="inline-flex min-h-jp-button w-full items-center justify-center rounded-jp-md border border-jp-brand px-4 text-jp-sm font-semibold text-jp-brand hover:bg-jp-brand-soft focus-visible:shadow-jp-focus"
                data-testid="login-register-link"
              >
                Sign up
              </Link>
            </div>
          ) : (
            <div className="space-y-2 text-center" data-testid="login-register-disabled">
              <p className="text-jp-sm font-semibold text-jp-text">Customer registration is closed</p>
              <p className="text-jp-sm text-jp-muted">Use an existing JetPakistan account to continue.</p>
            </div>
          )
        }
      >
        {isBookingAccountGate ? (
          <div className="mb-4" data-testid="login-booking-account-gate">
            <BookingProgress steps={accountProgress} ariaLabel="Booking progress" />
            <p className="mt-2 text-center text-xs text-jp-muted" data-testid="login-no-guest-notice">
              Continue as Guest is not available for this booking.
            </p>
          </div>
        ) : null}
        <LoginSessionNotice reason={reason} />
        <LoginForm returnPath={returnPath || undefined} showRegisterLink={canRegister} />
      </AuthShell>
    </>
  );
}

/** Client login — keeps /login free of server searchParams (soft-nav static). */
export function LoginPageClient() {
  return (
    <Suspense fallback={<AuthShell title="Log in to your account" description="Welcome back. Enter your details to continue."><div className="min-h-[12rem]" aria-busy="true" /></AuthShell>}>
      <LoginPageInner />
    </Suspense>
  );
}
