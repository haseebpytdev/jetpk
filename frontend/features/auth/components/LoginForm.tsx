"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { login } from "../services/auth-service";
import { mapFieldErrors } from "../utils/laravel-auth-api";
import { isNextJsOwnedPath } from "../utils/dashboard-allowlist";
import { AuthStatusBanner } from "./AuthStatusBanner";
import { PasswordField } from "./PasswordField";

const fieldClass =
  "min-h-jp-button w-full rounded-jp-md border border-jp-border bg-jp-surface px-4 text-jp-sm text-jp-text placeholder:text-jp-muted focus-visible:outline-none focus-visible:shadow-jp-focus";

export function LoginForm() {
  const router = useRouter();
  const [loginValue, setLoginValue] = useState("");
  const [password, setPassword] = useState("");
  const [remember, setRemember] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (submitting) return;

    setSubmitting(true);
    setFormError("");
    setFieldErrors({});

    const result = await login({ login: loginValue.trim(), password, remember });

    if (!result.ok) {
      setSubmitting(false);
      if (result.status === 429) {
        setFormError("Too many login attempts. Please wait a moment and try again.");
        return;
      }
      setFieldErrors(mapFieldErrors(result.fieldErrors));
      setFormError(result.fieldErrors?.login?.[0] ?? result.message);
      return;
    }

    if (result.requires_otp) {
      router.push("/login/otp");
      return;
    }

    if (isNextJsOwnedPath(result.redirect)) {
      router.push(result.redirect);
      router.refresh();
      return;
    }

    window.location.assign(result.redirect);
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="space-y-4">
      {formError ? <AuthStatusBanner tone="error" message={formError} live /> : null}

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
          disabled={submitting}
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
        disabled={submitting}
        error={fieldErrors.password}
      />

      <div className="flex items-center justify-between gap-3">
        <label className="inline-flex items-center gap-2 text-jp-sm text-jp-text">
          <input
            type="checkbox"
            name="remember"
            checked={remember}
            disabled={submitting}
            onChange={(event) => setRemember(event.target.checked)}
            className="h-4 w-4 rounded border-jp-border text-jp-primary focus-visible:shadow-jp-focus"
          />
          Remember me
        </label>
        <a href="/forgot-password" className="text-jp-sm font-semibold text-jp-primary hover:underline">
          Forgot password?
        </a>
      </div>

      <PrimaryButton type="submit" className="w-full" disabled={submitting}>
        {submitting ? "Signing in…" : "Sign in"}
      </PrimaryButton>

      <p className="text-center text-jp-sm text-jp-muted">
        New to JetPakistan?{" "}
        <a href="/register" className="font-semibold text-jp-primary hover:underline">
          Create an account
        </a>
      </p>
      <p className="text-center text-jp-sm text-jp-muted">
        Travel agency?{" "}
        <a href="/agent/register" className="font-semibold text-jp-primary hover:underline">
          Apply as an Agent
        </a>
      </p>
    </form>
  );
}
