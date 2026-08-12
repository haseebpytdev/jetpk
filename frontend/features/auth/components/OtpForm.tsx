"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { useAuthSubmissionReady } from "../hooks/useAuthSubmissionReady";
import { fetchOtpChallenge, resendOtp, verifyOtp } from "../services/auth-service";
import { mapFieldErrors } from "../utils/laravel-auth-api";
import { AuthStatusBanner } from "./AuthStatusBanner";

const fieldClass =
  "min-h-jp-button w-full rounded-jp-md border border-jp-border bg-jp-surface px-4 text-center text-2xl tracking-[0.35em] text-jp-text placeholder:text-jp-muted focus-visible:outline-none focus-visible:shadow-jp-focus";

function resolveOtpErrorMessage(result: Extract<Awaited<ReturnType<typeof verifyOtp>>, { ok: false }>): string {
  if (result.code === "network") {
    return "Network error. Check your connection and try again.";
  }
  if (result.code === "csrf_expired") {
    return "Your session expired. Please try again.";
  }
  if (result.status === 429 || result.code === "rate_limit") {
    return "Too many attempts. Please wait a moment and try again.";
  }
  return result.fieldErrors?.otp?.[0] ?? result.message;
}

export function OtpForm() {
  const router = useRouter();
  const { ready, error: readinessError } = useAuthSubmissionReady();
  const [otp, setOtp] = useState("");
  const [maskedEmail, setMaskedEmail] = useState<string | null>(null);
  const [cooldown, setCooldown] = useState(0);
  const [submitting, setSubmitting] = useState(false);
  const [resending, setResending] = useState(false);
  const [formError, setFormError] = useState("");
  const [statusMessage, setStatusMessage] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  const loadChallenge = useCallback(async () => {
    const challenge = await fetchOtpChallenge();
    if (!challenge.has_challenge) {
      router.replace("/login");
      return;
    }
    setMaskedEmail(challenge.masked_email ?? null);
    setCooldown(challenge.resend_available_in ?? 0);
  }, [router]);

  useEffect(() => {
    if (!ready) return;
    void loadChallenge();
  }, [loadChallenge, ready]);

  useEffect(() => {
    if (cooldown <= 0) return;
    const timer = window.setInterval(() => {
      setCooldown((value) => Math.max(0, value - 1));
    }, 1000);
    return () => window.clearInterval(timer);
  }, [cooldown]);

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!ready || submitting) return;

    setSubmitting(true);
    setFormError("");
    setStatusMessage("");
    setFieldErrors({});

    const result = await verifyOtp({ otp: otp.replace(/\D/g, "").slice(0, 6) });

    if (!result.ok) {
      setSubmitting(false);
      setFieldErrors(mapFieldErrors(result.fieldErrors));
      const otpError = resolveOtpErrorMessage(result);
      setFormError(otpError);
      if (otpError.toLowerCase().includes("expired") || otpError.toLowerCase().includes("sign in again")) {
        setTimeout(() => router.replace("/login"), 1500);
      }
      return;
    }

    const destination = result.dashboard_url ?? result.redirect;
    // Hard navigation after OTP verifies the session cookie across the
    // /laravel rewrite boundary (same rationale as LoginForm).
    window.location.assign(destination);
  }

  async function handleResend() {
    if (!ready || resending || cooldown > 0) return;
    setResending(true);
    setFormError("");
    const result = await resendOtp();
    setResending(false);
    if (!result.ok) {
      setFormError(result.message);
      return;
    }
    setStatusMessage(result.message);
    setCooldown(result.resend_available_in);
  }

  function handleOtpChange(value: string) {
    const digits = value.replace(/\D/g, "").slice(0, 6);
    setOtp(digits);
  }

  const bannerError = readinessError ?? formError;
  const controlsDisabled = !ready || submitting;

  return (
    <form onSubmit={handleSubmit} noValidate className="space-y-4">
      {statusMessage ? <AuthStatusBanner tone="success" message={statusMessage} live /> : null}
      {bannerError ? <AuthStatusBanner tone="error" message={bannerError} live /> : null}
      {!ready && !readinessError ? (
        <AuthStatusBanner tone="info" message="Preparing secure sign-in…" live />
      ) : null}

      <p className="text-jp-sm text-jp-muted">
        {maskedEmail
          ? `Enter the 6-digit code sent to ${maskedEmail}.`
          : "Enter the 6-digit verification code sent to your email."}
      </p>

      <div>
        <label htmlFor="otp" className="mb-1.5 block text-jp-sm font-medium text-jp-text">
          Verification code <span className="text-jp-danger">*</span>
        </label>
        <input
          id="otp"
          name="otp"
          inputMode="numeric"
          autoComplete="one-time-code"
          pattern="\d{6}"
          maxLength={6}
          required
          disabled={controlsDisabled}
          value={otp}
          onChange={(event) => handleOtpChange(event.target.value)}
          aria-invalid={fieldErrors.otp ? true : undefined}
          aria-describedby={fieldErrors.otp ? "otp-error" : undefined}
          className={fieldClass}
        />
        {fieldErrors.otp ? (
          <p id="otp-error" role="alert" className="mt-1 text-jp-xs text-jp-danger">
            {fieldErrors.otp}
          </p>
        ) : null}
      </div>

      <PrimaryButton type="submit" className="w-full" disabled={controlsDisabled || otp.length !== 6}>
        {!ready ? "Preparing secure sign-in…" : submitting ? "Verifying…" : "Verify and continue"}
      </PrimaryButton>

      <div className="flex items-center justify-between gap-3 text-jp-sm">
        <button
          type="button"
          onClick={() => void handleResend()}
          disabled={!ready || resending || cooldown > 0}
          className="font-semibold text-jp-primary hover:underline disabled:cursor-not-allowed disabled:text-jp-muted"
        >
          {cooldown > 0 ? `Resend in ${cooldown}s` : resending ? "Sending…" : "Resend code"}
        </button>
        <a href="/login" className="text-jp-muted hover:text-jp-text">
          Back to sign in
        </a>
      </div>
    </form>
  );
}
