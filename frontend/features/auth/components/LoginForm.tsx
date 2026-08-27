"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { ensureLaravelCsrfToken } from "@/lib/api";
import { useState } from "react";
import { useAuthSubmissionReady } from "../hooks/useAuthSubmissionReady";
import { login } from "../services/auth-service";
import { sanitizeCheckoutReturnUrl } from "../utils/checkout-return-allowlist";
import { mapFieldErrors } from "../utils/laravel-auth-api";
import { AuthStatusBanner } from "./AuthStatusBanner";
import { PasswordField } from "./PasswordField";

const fieldClass =
  "min-h-jp-button w-full rounded-jp-md border border-jp-border bg-jp-surface px-4 text-jp-sm text-jp-text placeholder:text-jp-muted focus-visible:outline-none focus-visible:shadow-jp-focus";

function resolveLoginErrorMessage(result: Extract<Awaited<ReturnType<typeof login>>, { ok: false }>): string {
  if (result.code === "network") {
    return "Network error. Check your connection and try again.";
  }
  if (result.code === "csrf_expired") {
    return "Your session expired. Please try again.";
  }
  if (result.status === 429 || result.code === "rate_limit") {
    return "Too many login attempts. Please wait a moment and try again.";
  }
  return result.fieldErrors?.login?.[0] ?? result.message;
}

type LoginFormProps = {
  /** Safe internal path to resume after auth (e.g. /groups/{id}/passengers). */
  returnPath?: string;
  /** When set, called instead of hard navigation (modal can close then navigate). */
  onSuccessNavigate?: (path: string) => void;
  showRegisterLink?: boolean;
  compact?: boolean;
};

export function LoginForm({
  returnPath,
  onSuccessNavigate,
  showRegisterLink = true,
  compact = false,
}: LoginFormProps = {}) {
  const { ready, error: readinessError } = useAuthSubmissionReady();
  const [loginValue, setLoginValue] = useState("");
  const [password, setPassword] = useState("");
  const [remember, setRemember] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  const safeReturn = returnPath ? sanitizeCheckoutReturnUrl(returnPath, "") : "";

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!ready || submitting) return;

    setSubmitting(true);
    setFormError("");
    setFieldErrors({});

    try {
      const csrf = await ensureLaravelCsrfToken(true);
      if (!csrf) {
        setSubmitting(false);
        setFormError("Unable to prepare secure sign-in. Please refresh and try again.");
        return;
      }

      const result = await login({
        login: loginValue.trim(),
        password,
        remember,
        ...(safeReturn ? { redirect: safeReturn } : {}),
      });

      if (!result.ok) {
        setSubmitting(false);
        setFieldErrors(mapFieldErrors(result.fieldErrors));
        setFormError(resolveLoginErrorMessage(result));
        return;
      }

      if (result.requires_otp) {
        const otpUrl = safeReturn
          ? `/login/otp?redirect=${encodeURIComponent(safeReturn)}`
          : "/login/otp";
        window.location.assign(otpUrl);
        return;
      }

      const destination = safeReturn || result.redirect;
      if (onSuccessNavigate) {
        onSuccessNavigate(destination);
        return;
      }

      window.location.assign(destination);
    } catch {
      setSubmitting(false);
      setFormError("Network error. Check your connection and try again.");
    }
  }

  const bannerError = readinessError ?? formError;
  const controlsDisabled = !ready || submitting;
  const registerHref = safeReturn
    ? `/register?redirect=${encodeURIComponent(safeReturn)}`
    : "/register";

  return (
    <form
      onSubmit={handleSubmit}
      noValidate
      className={compact ? "space-y-3 font-[Inter,system-ui,sans-serif]" : "space-y-4"}
      data-testid="login-form"
    >
      {bannerError ? <AuthStatusBanner tone="error" message={bannerError} live /> : null}
      {!ready && !readinessError ? (
        <AuthStatusBanner tone="info" message="Preparing secure sign-in…" live />
      ) : null}

      <div>
        <label htmlFor="login" className="mb-1.5 block text-jp-sm font-medium text-jp-text">
          Email or username <span className="text-jp-danger">*</span>
        </label>
        <input
          id="login"
          name="login"
          type="text"
          autoComplete="username"
          required
          disabled={controlsDisabled}
          value={loginValue}
          onChange={(event) => setLoginValue(event.target.value)}
          aria-invalid={fieldErrors.login ? true : undefined}
          aria-describedby={fieldErrors.login ? "login-error" : undefined}
          className={fieldClass}
        />
        {fieldErrors.login ? (
          <p id="login-error" role="alert" className="mt-1 text-jp-xs text-jp-danger">
            {fieldErrors.login}
          </p>
        ) : null}
      </div>

      <PasswordField
        label="Password"
        value={password}
        onChange={setPassword}
        disabled={controlsDisabled}
        error={fieldErrors.password}
      />

      <div className="flex items-center justify-between gap-3">
        <label className="inline-flex items-center gap-2 text-jp-sm text-jp-text">
          <input
            type="checkbox"
            name="remember"
            checked={remember}
            disabled={controlsDisabled}
            onChange={(event) => setRemember(event.target.checked)}
            className="h-4 w-4 rounded border-jp-border text-jp-primary focus-visible:shadow-jp-focus"
          />
          Remember me
        </label>
        <a href="/forgot-password" className="text-jp-sm font-semibold text-jp-primary hover:underline">
          Forgot password?
        </a>
      </div>

      <PrimaryButton type="submit" className="w-full" disabled={controlsDisabled} data-testid="login-submit">
        {!ready ? "Preparing secure sign-in…" : submitting ? "Signing in…" : "Sign in"}
      </PrimaryButton>

      {showRegisterLink ? (
        <p className="text-center text-jp-sm text-jp-muted">
          No account?{" "}
          <a href={registerHref} className="font-semibold text-jp-primary hover:underline" data-testid="login-register-link">
            Create account
          </a>
        </p>
      ) : null}

      {!compact ? (
        <p className="text-center text-jp-sm text-jp-muted">
          Travel agency?{" "}
          <a href="/agent/register" className="font-semibold text-jp-primary hover:underline">
            Apply as an Agent
          </a>
        </p>
      ) : null}
    </form>
  );
}
