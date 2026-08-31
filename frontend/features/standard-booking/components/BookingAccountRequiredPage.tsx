"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { BookingProgress } from "@/features/booking-progress";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { fetchSessionBootstrap } from "@/features/auth/services/session-service";
import { sanitizeCheckoutReturnUrl } from "@/features/auth/utils/checkout-return-allowlist";
import { fetchCommerceGates } from "@/features/standard-booking/services/commerce-gates-service";

function accountProgress(active: "account" | "traveler") {
  return [
    { key: "account", label: "Account", state: active === "account" ? "current" : "completed" },
    { key: "passenger_details", label: "Travelers", state: active === "traveler" ? "current" : "upcoming" },
    { key: "review", label: "Review", state: "upcoming" },
    { key: "payment", label: "Payment", state: "upcoming" },
    { key: "confirmation", label: "Confirmation", state: "upcoming" },
  ] as const;
}

export function BookingAccountRequiredPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const rawRedirect = searchParams.get("redirect") ?? "/booking/passengers";
  const resumePath = sanitizeCheckoutReturnUrl(rawRedirect, "/booking/passengers");
  const [registrationEnabled, setRegistrationEnabled] = useState(true);
  const [checking, setChecking] = useState(true);

  const progress = useMemo(() => [...accountProgress("account")], []);

  useEffect(() => {
    let cancelled = false;
    void (async () => {
      try {
        const [bootstrap, gates] = await Promise.all([fetchSessionBootstrap(), fetchCommerceGates()]);
        if (cancelled) return;
        if (bootstrap.authenticated) {
          router.replace(resumePath);
          return;
        }
        setRegistrationEnabled(gates.customer_registration_enabled !== false);
      } finally {
        if (!cancelled) setChecking(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [resumePath, router]);

  const loginHref = `/login?redirect=${encodeURIComponent(resumePath)}`;
  const registerHref = `/register?redirect=${encodeURIComponent(resumePath)}`;

  return (
    <div className="mx-auto w-full max-w-jp-container px-jp-xl py-8" data-testid="booking-account-required">
      <BookingProgress steps={[...progress]} ariaLabel="Booking progress" className="mb-6" />
      <section className="mx-auto max-w-lg rounded-jp-lg border border-jp-border bg-jp-surface p-5 shadow-jp-card sm:p-6">
        <p className="text-[11px] font-semibold uppercase tracking-wide text-jp-primary">Account required</p>
        <h1 className="mt-1 text-xl font-semibold text-jp-text">Sign in to continue booking</h1>
        <p className="mt-2 text-sm text-jp-muted">
          Guest checkout is currently unavailable. Sign in
          {registrationEnabled ? " or create an account" : ""} to continue to Traveler Details with your selected
          flight.
        </p>

        {checking ? (
          <p className="mt-4 text-sm text-jp-muted">Checking account options…</p>
        ) : (
          <div className="mt-5 flex flex-col gap-3">
            <PrimaryButton
              type="button"
              className="w-full"
              data-testid="account-required-login"
              onClick={() => {
                window.location.assign(loginHref);
              }}
            >
              Log in
            </PrimaryButton>
            {registrationEnabled ? (
              <Link
                href={registerHref}
                className="inline-flex min-h-jp-button w-full items-center justify-center rounded-jp-button border border-jp-border px-4 text-sm font-semibold text-jp-text"
                data-testid="account-required-register"
              >
                Register
              </Link>
            ) : null}
            <p className="text-center text-xs text-jp-muted" data-testid="account-required-no-guest">
              Continue as Guest is not available for this booking.
            </p>
          </div>
        )}
      </section>
    </div>
  );
}
