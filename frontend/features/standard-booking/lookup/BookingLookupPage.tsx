"use client";

import { useId, useRef, useState } from "react";
import Link from "next/link";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import {
  TurnstileUnavailableState,
  TurnstileValidationMessage,
  TurnstileWidget,
  useTurnstileToken,
} from "@/features/security/turnstile";
import { submitBookingLookup } from "./booking-lookup-service";
import { BLADE_LOOKUP_FALLBACK_PATH } from "./guest-redirect";

const fieldClass =
  "mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2 focus-visible:outline-none focus-visible:shadow-jp-focus";

export function BookingLookupPage() {
  const errorSummaryId = useId();
  const bookingReferenceRef = useRef<HTMLInputElement>(null);
  const [bookingReference, setBookingReference] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitting, setSubmitting] = useState(false);

  const {
    config,
    loading: turnstileLoading,
    token,
    tokenRequired,
    tokenExpired,
    tokenError,
    scriptFailed,
    resetSignal,
    setToken,
    markExpired,
    markError,
    markScriptFailed,
    resetToken,
  } = useTurnstileToken();

  const turnstileEnabled = tokenRequired && Boolean(config?.site_key);
  const submitDisabled =
    submitting ||
    turnstileLoading ||
    (turnstileEnabled && !token) ||
    tokenExpired ||
    tokenError;

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (submitDisabled) return;

    if (!bookingReference.trim() || !email.trim()) {
      const nextErrors: Record<string, string> = {};
      if (!bookingReference.trim()) nextErrors.booking_reference = "Booking reference is required.";
      if (!email.trim()) nextErrors.email = "Email address is required.";
      setFieldErrors(nextErrors);
      setError("Please complete the required fields.");
      bookingReferenceRef.current?.focus();
      return;
    }

    setSubmitting(true);
    setError(null);
    setFieldErrors({});

    const result = await submitBookingLookup({
      booking_reference: bookingReference,
      email,
      phone,
      ...(turnstileEnabled && token ? { "cf-turnstile-response": token } : {}),
    });

    setSubmitting(false);

    if (result.ok) {
      window.location.assign(result.redirectUrl);
      return;
    }

    if (result.turnstileRejected || result.fieldErrors?.["cf-turnstile-response"]) {
      resetToken();
      setError(result.message);
      return;
    }

    if (result.genericFailure) {
      resetToken();
      setError(result.message);
      return;
    }

    if (result.fieldErrors) {
      const mapped: Record<string, string> = {};
      Object.entries(result.fieldErrors).forEach(([key, messages]) => {
        if (messages[0]) mapped[key] = messages[0];
      });
      setFieldErrors(mapped);
    }

    setError(result.message);
    if (result.rateLimited) {
      resetToken();
    }
  };

  return (
    <div className="mx-auto max-w-4xl px-4 py-8" data-testid="booking-lookup-page">
      <header className="max-w-2xl">
        <p className="text-jp-xs font-semibold uppercase tracking-wide text-jp-primary">Manage booking</p>
        <h1 className="mt-1 text-2xl font-semibold text-jp-text">Lookup your booking</h1>
        <p className="mt-2 text-jp-sm text-jp-muted">
          Enter your booking reference and the email used when you booked. We verify your details before showing sensitive information.
        </p>
      </header>

      <div className="mt-8 grid gap-6 lg:grid-cols-[1fr_1.2fr]">
        <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 text-jp-sm text-jp-muted">
          <h2 className="text-jp-base font-semibold text-jp-text">How lookup works</h2>
          <p className="mt-2">When your details match our records, you receive secure access to your booking documents and status.</p>
          <p className="mt-3">For privacy, we use a generic message when details do not match.</p>
        </section>

        <form
          className="rounded-jp-lg border border-jp-border bg-jp-surface p-4"
          onSubmit={(event) => void handleSubmit(event)}
          noValidate
          aria-describedby={error ? errorSummaryId : undefined}
        >
          <h2 className="text-jp-base font-semibold text-jp-text">Enter your details</h2>

          {error ? (
            <div
              id={errorSummaryId}
              className="mt-3 rounded-jp-md border border-red-200 bg-red-50 p-3 text-jp-sm text-red-800"
              role="alert"
              data-testid="lookup-error"
              aria-live="polite"
            >
              {error}
            </div>
          ) : null}

          <div className="mt-4 space-y-4">
            <label className="block text-jp-sm">
              <span className="font-medium text-jp-text">
                Booking reference <span className="text-red-700">*</span>
              </span>
              <input
                ref={bookingReferenceRef}
                className={`${fieldClass} ${fieldErrors.booking_reference ? "border-red-400" : ""}`}
                name="booking_reference"
                value={bookingReference}
                onChange={(event) => setBookingReference(event.target.value)}
                autoComplete="off"
                required
                aria-invalid={Boolean(fieldErrors.booking_reference)}
              />
              {fieldErrors.booking_reference ? (
                <span className="mt-1 block text-jp-xs text-red-700">{fieldErrors.booking_reference}</span>
              ) : null}
            </label>

            <label className="block text-jp-sm">
              <span className="font-medium text-jp-text">
                Email address <span className="text-red-700">*</span>
              </span>
              <input
                className={`${fieldClass} ${fieldErrors.email ? "border-red-400" : ""}`}
                name="email"
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                autoComplete="email"
                required
                aria-invalid={Boolean(fieldErrors.email)}
              />
              {fieldErrors.email ? <span className="mt-1 block text-jp-xs text-red-700">{fieldErrors.email}</span> : null}
            </label>

            <label className="block text-jp-sm">
              <span className="font-medium text-jp-text">Phone (optional)</span>
              <input
                className={fieldClass}
                name="phone"
                value={phone}
                onChange={(event) => setPhone(event.target.value)}
                autoComplete="tel"
              />
            </label>
          </div>

          {turnstileLoading ? (
            <p className="mt-4 text-jp-sm text-jp-muted" aria-live="polite">
              Loading security check…
            </p>
          ) : null}

          {turnstileEnabled && !scriptFailed ? (
            <div className="mt-4">
              <TurnstileWidget
                siteKey={config!.site_key!}
                resetSignal={resetSignal}
                onToken={(nextToken) => {
                  setToken(nextToken);
                  setError(null);
                }}
                onExpire={markExpired}
                onError={markError}
                onScriptError={markScriptFailed}
                compact
              />
              {tokenExpired ? (
                <TurnstileValidationMessage message="Security check expired. Please complete it again." />
              ) : null}
              {tokenError ? (
                <TurnstileValidationMessage message="Security check failed. Please try again." />
              ) : null}
              {!token && !tokenExpired && !tokenError ? (
                <p className="mt-2 text-jp-xs text-jp-muted">Complete the security check to continue.</p>
              ) : null}
            </div>
          ) : null}

          {turnstileEnabled && scriptFailed ? (
            <div className="mt-4">
              <TurnstileUnavailableState bladeFallbackHref={BLADE_LOOKUP_FALLBACK_PATH} />
            </div>
          ) : null}

          <p className="mt-4 text-jp-xs text-jp-muted">For privacy, access is only granted when your details match the booking.</p>

          <PrimaryButton
            type="submit"
            className="mt-4"
            disabled={submitDisabled || (turnstileEnabled && scriptFailed)}
            data-testid="lookup-submit"
            aria-disabled={submitDisabled || (turnstileEnabled && scriptFailed)}
          >
            {submitting ? "Looking up…" : "Lookup booking"}
          </PrimaryButton>
        </form>
      </div>

      <nav className="mt-6 flex flex-wrap gap-4 text-jp-sm print:hidden">
        <Link href="/support" className="text-jp-primary focus-visible:shadow-jp-focus">
          Need help? Contact support
        </Link>
        <Link href={BLADE_LOOKUP_FALLBACK_PATH} className="text-jp-primary focus-visible:shadow-jp-focus" data-testid="blade-lookup-fallback">
          Use secure Blade lookup
        </Link>
      </nav>
    </div>
  );
}
