import { redirect } from "next/navigation";
import Link from "next/link";
import { AuthShell, LoginForm } from "@/features/auth";
import { LoginSessionNotice } from "@/features/auth/components/LoginSessionNotice";
import { BookingProgress } from "@/features/booking-progress";
import { fetchSessionBootstrapFromCookies } from "@/features/auth/services/session-service";
import { cookies } from "next/headers";
import { sanitizeCheckoutReturnUrl } from "@/features/auth/utils/checkout-return-allowlist";
import { sanitizeDashboardUrl } from "@/features/auth/utils/dashboard-allowlist";
import { laravelApiPath } from "@/services/flight-search";

type LoginPageProps = {
  searchParams: Promise<{
    reason?: string;
    redirect?: string;
    checkout_return?: string;
    booking_gate?: string;
  }>;
};

async function registrationEnabled(): Promise<boolean> {
  try {
    const response = await fetch(laravelApiPath("/booking/commerce-gates"), {
      headers: { Accept: "application/json" },
      cache: "no-store",
    });
    if (!response.ok) return true;
    const payload = (await response.json()) as { customer_registration_enabled?: boolean };
    return payload.customer_registration_enabled !== false;
  } catch {
    return true;
  }
}

export default async function LoginPage({ searchParams }: LoginPageProps) {
  const {
    reason,
    redirect: redirectParam,
    checkout_return: checkoutReturn,
    booking_gate: bookingGate,
  } = await searchParams;
  const returnPath = sanitizeCheckoutReturnUrl(checkoutReturn || redirectParam, "");
  const cookieStore = await cookies();
  const bootstrap = await fetchSessionBootstrapFromCookies(cookieStore.getAll());
  if (bootstrap.authenticated) {
    redirect(
      returnPath ||
        sanitizeDashboardUrl(bootstrap.landing_route ?? bootstrap.dashboard_url, "/"),
    );
  }

  const isBookingAccountGate = bookingGate === "account";
  const canRegister = isBookingAccountGate ? await registrationEnabled() : true;
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
  );
}
