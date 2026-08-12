"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { ensureLaravelCsrfToken } from "@/lib/api";
import { useState } from "react";
import { useAuthSubmissionReady } from "../hooks/useAuthSubmissionReady";
import { login } from "../services/auth-service";
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

export function LoginForm() {
  const { ready, error: readinessError } = useAuthSubmissionReady();
  const [loginValue, setLoginValue] = useState("");
  const [password, setPassword] = useState("");
  const [remember, setRemember] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!ready || submitting) return;

    setSubmitting(true);
    setFormError("");
    setFieldErrors({});

    try {
      // Refresh CSRF immediately before credential POST so cookie/body token
      // stay aligned with the Laravel session established through /laravel rewrite.
      const csrf = await ensureLaravelCsrfToken(true);
      if (!csrf) {
        setSubmitting(false);
        setFormError("Unable to prepare secure sign-in. Please refresh and try again.");
        return;
      }

      const result = await login({ login: loginValue.trim(), password, remember });

      if (!result.ok) {
        setSubmitting(false);
        setFieldErrors(mapFieldErrors(result.fieldErrors));
        setFormError(resolveLoginErrorMessage(result));
        return;
      }

      if (result.requires_otp) {
        window.location.assign("/login/otp");
        return;
      }

      // Hard navigation after session establishment — soft App Router transitions
      // can race the Set-Cookie from /laravel/login and surface as a false
      // client "network" failure while the session cookie is already valid.
      window.location.assign(result.redirect);
    } catch {
      setSubmitting(false);
      setFormError("Network error. Check your connection and try again.");
    }
  }

  const bannerError = readinessError ?? formError;
  const controlsDisabled = !ready || submitting;

  return (
    <form onSubmit={handleSubmit} noValidate className="space-y-4">
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

      <PrimaryButton type="submit" className="w-full" disabled={controlsDisabled}>
        {!ready ? "Preparing secure sign-in…" : submitting ? "Signing in…" : "Sign in"}
      </PrimaryButton>

      <p className="text-center text-jp-sm text-jp-muted">
        Travel agency?{" "}
        <a href="/agent/register" className="font-semibold text-jp-primary hover:underline">
          Apply as an Agent
        </a>
      </p>
    </form>
  );
}
