"use client";

import { useId, useRef, useState } from "react";
import Link from "next/link";
import { PageContainer } from "@/components/layout/PageContainer";
import { ImageSlot } from "@/components/ui/ImageSlot";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { BenefitStrip } from "@/features/public-visual/components/BenefitStrip";
import {
  TurnstileUnavailableState,
  TurnstileValidationMessage,
  TurnstileWidget,
  useTurnstileToken,
} from "@/features/security/turnstile";
import { submitBookingLookup } from "./booking-lookup-service";

const fieldClass =
  "mt-1 w-full rounded-jp-md border border-jp-border bg-jp-surface px-3 py-2.5 text-jp-sm text-jp-text placeholder:text-jp-muted focus-visible:outline-none focus-visible:shadow-jp-focus";

const LOOKUP_TRUST_CHIPS = [
  { label: "Secure lookup" },
  { label: "Privacy protected" },
  { label: "Support available" },
  { label: "Fast access" },
];

const LOOKUP_HERO_IMAGE = "/images/auth/auth-illustration.svg";

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
    <div data-testid="booking-lookup-page">
      <section className="relative overflow-hidden border-b border-jp-border bg-jp-page">
        <div className="absolute inset-0">
          <ImageSlot
            src={LOOKUP_HERO_IMAGE}
            alt=""
            decorative
            width={1440}
            height={420}
            className="!max-w-none h-full w-full !rounded-none"
            objectFit="cover"
            fallbackLabel="Manage booking"
          />
          <div
            className="absolute inset-0 bg-gradient-to-b from-black/30 via-black/15 to-jp-page/95 dark:from-black/50 dark:via-black/30"
            aria-hidden="true"
          />
        </div>
        <PageContainer className="relative z-10 py-jp-2xl">
          <header className="max-w-2xl text-white">
            <p className="text-jp-xs font-semibold uppercase tracking-[0.16em] text-white/85">Manage booking</p>
            <h1 className="mt-2 font-sans text-jp-h1 font-bold leading-tight">
              Manage your <span className="text-jp-primary-soft">booking</span>
            </h1>
            <p className="mt-3 max-w-xl text-jp-sm leading-relaxed text-white/90">
              View trip details and get support. Access is granted only when your details match our records.
            </p>
          </header>
        </PageContainer>
      </section>

      <PageContainer className="relative z-20 -mt-10 pb-jp-xl sm:-mt-12">
        <div className="grid gap-jp-lg lg:grid-cols-[1fr_1.15fr]">
          <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-jp-lg shadow-jp-sm">
            <h2 className="text-jp-base font-semibold text-jp-text">How lookup works</h2>
            <p className="mt-2 text-jp-sm text-jp-muted">
              Enter your booking reference and the email used when you booked. We verify your details before showing sensitive information.
            </p>
            <p className="mt-3 text-jp-sm text-jp-muted">
              For privacy, we use a generic message when details do not match.
            </p>
            <div className="mt-jp-lg rounded-jp-md border border-jp-border bg-jp-surface-muted p-jp-md">
              <h3 className="text-jp-sm font-semibold text-jp-text">Your security matters</h3>
              <p className="mt-2 text-jp-xs text-jp-muted">
                Lookup is protected by verification checks. We never expose whether a booking exists without matching credentials.
              </p>
            </div>
            <Link href="/support" className="mt-jp-md inline-flex text-jp-sm font-semibold text-jp-primary focus-visible:shadow-jp-focus">
              Need help? Contact support
            </Link>
          </section>

          <form
            className="rounded-jp-lg border border-jp-border bg-jp-surface p-jp-lg shadow-jp-md"
            onSubmit={(event) => void handleSubmit(event)}
            noValidate
            aria-describedby={error ? errorSummaryId : undefined}
            data-testid="booking-lookup-form"
          >
            <h2 className="text-jp-h3 font-bold text-jp-text">Look up your booking</h2>
            <p className="mt-1 text-jp-sm text-jp-muted">Enter your booking details to access your trip.</p>

            {error ? (
              <div
                id={errorSummaryId}
                className="mt-jp-md rounded-jp-md border border-jp-danger/30 bg-jp-danger/10 p-3 text-jp-sm text-jp-text"
                role="alert"
                data-testid="lookup-error"
                aria-live="polite"
              >
                {error}
              </div>
            ) : null}

            <div className="mt-jp-md space-y-4">
              <label className="block text-jp-sm">
                <span className="font-medium text-jp-text">
                  Booking reference <span className="text-jp-danger">*</span>
                </span>
                <input
                  ref={bookingReferenceRef}
                  className={`${fieldClass} ${fieldErrors.booking_reference ? "border-jp-danger" : ""}`}
                  name="booking_reference"
                  value={bookingReference}
                  onChange={(event) => setBookingReference(event.target.value)}
                  autoComplete="off"
                  required
                  aria-invalid={Boolean(fieldErrors.booking_reference)}
                  placeholder="Enter your booking reference"
                />
                {fieldErrors.booking_reference ? (
                  <span className="mt-1 block text-jp-xs text-jp-danger">{fieldErrors.booking_reference}</span>
                ) : null}
              </label>

              <label className="block text-jp-sm">
                <span className="font-medium text-jp-text">
                  Email address <span className="text-jp-danger">*</span>
                </span>
                <input
                  className={`${fieldClass} ${fieldErrors.email ? "border-jp-danger" : ""}`}
                  name="email"
                  type="email"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  autoComplete="email"
                  required
                  aria-invalid={Boolean(fieldErrors.email)}
                  placeholder="Enter the email used when booking"
                />
                {fieldErrors.email ? <span className="mt-1 block text-jp-xs text-jp-danger">{fieldErrors.email}</span> : null}
              </label>

              <label className="block text-jp-sm">
                <span className="font-medium text-jp-text">Phone (optional)</span>
                <input
                  className={fieldClass}
                  name="phone"
                  value={phone}
                  onChange={(event) => setPhone(event.target.value)}
                  autoComplete="tel"
                  placeholder="Optional contact number"
                />
              </label>
            </div>

            {turnstileLoading ? (
              <p className="mt-4 text-jp-sm text-jp-muted" aria-live="polite" data-testid="lookup-turnstile-loading">
                Loading security check…
              </p>
            ) : null}

            {turnstileEnabled && !scriptFailed ? (
              <div className="mt-4" data-testid="lookup-turnstile">
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
                <TurnstileUnavailableState
                  recoveryHref="/support"
                  onRetry={() => {
                    resetToken();
                    window.location.reload();
                  }}
                />
              </div>
            ) : null}

            <PrimaryButton
              type="submit"
              className="mt-jp-md w-full sm:w-auto"
              disabled={submitDisabled || (turnstileEnabled && scriptFailed)}
              data-testid="lookup-submit"
              aria-disabled={submitDisabled || (turnstileEnabled && scriptFailed)}
            >
              {submitting ? "Looking up…" : "Find booking"}
            </PrimaryButton>
          </form>
        </div>

        <div className="mt-jp-xl rounded-jp-lg border border-jp-border bg-jp-surface p-jp-lg">
          <BenefitStrip items={LOOKUP_TRUST_CHIPS} />
        </div>
      </PageContainer>
    </div>
  );
}
