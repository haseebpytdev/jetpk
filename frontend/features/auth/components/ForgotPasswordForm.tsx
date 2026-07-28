"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { useState } from "react";
import { requestPasswordReset } from "../services/password-reset-service";
import { mapFieldErrors } from "../utils/laravel-auth-api";
import { AuthStatusBanner } from "./AuthStatusBanner";

const fieldClass =
  "min-h-jp-button w-full rounded-jp-md border border-jp-border bg-jp-surface px-4 text-jp-sm text-jp-text placeholder:text-jp-muted focus-visible:outline-none focus-visible:shadow-jp-focus";

export function ForgotPasswordForm() {
  const [email, setEmail] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState("");
  const [successMessage, setSuccessMessage] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (submitting) return;

    setSubmitting(true);
    setFormError("");
    setSuccessMessage("");
    setFieldErrors({});

    const result = await requestPasswordReset({ email: email.trim().toLowerCase() });

    setSubmitting(false);

    if (!result.ok) {
      if (result.status === 429) {
        setFormError("Too many requests. Please wait a moment and try again.");
        return;
      }
      setFieldErrors(mapFieldErrors(result.fieldErrors));
      setFormError(result.message);
      return;
    }

    setSuccessMessage(result.message);
  }

  if (successMessage) {
    return (
      <div className="space-y-4">
        <AuthStatusBanner tone="success" message={successMessage} live />
        <a href="/login" className="inline-flex text-jp-sm font-semibold text-jp-primary hover:underline">
          Return to sign in
        </a>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="space-y-4">
      {formError ? <AuthStatusBanner tone="error" message={formError} live /> : null}
      <p className="text-jp-sm text-jp-muted">
        Enter the email address associated with your account. If an account exists, we will email password reset instructions.
      </p>
      <div>
        <label htmlFor="email" className="mb-1.5 block text-jp-sm font-medium text-jp-text">
          Email <span className="text-jp-danger">*</span>
        </label>
        <input
          id="email"
          name="email"
          type="email"
          autoComplete="email"
          required
          disabled={submitting}
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          className={fieldClass}
        />
        {fieldErrors.email ? <p className="mt-1 text-jp-xs text-jp-danger">{fieldErrors.email}</p> : null}
      </div>
      <PrimaryButton type="submit" className="w-full" disabled={submitting}>
        {submitting ? "Sending…" : "Send reset link"}
      </PrimaryButton>
      <p className="text-center text-jp-sm text-jp-muted">
        <a href="/login" className="font-semibold text-jp-primary hover:underline">
          Back to sign in
        </a>
      </p>
    </form>
  );
}
