"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { useState } from "react";
import { resetPassword } from "../services/password-reset-service";
import { mapFieldErrors } from "../utils/laravel-auth-api";
import { AuthStatusBanner } from "./AuthStatusBanner";
import { PasswordField } from "./PasswordField";

export function ResetPasswordForm({ token, email: initialEmail }: { token: string; email?: string }) {
  const [email, setEmail] = useState(initialEmail ?? "");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
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

    const result = await resetPassword({
      token,
      email: email.trim().toLowerCase(),
      password,
      password_confirmation: passwordConfirmation,
    });

    if (!result.ok) {
      setSubmitting(false);
      setFieldErrors(mapFieldErrors(result.fieldErrors));
      setFormError(result.fieldErrors?.email?.[0] ?? result.message);
      return;
    }

    setSuccessMessage(result.message ?? "Your password has been reset.");
    window.location.assign(result.redirect);
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="space-y-4">
      {successMessage ? <AuthStatusBanner tone="success" message={successMessage} live /> : null}
      {formError ? <AuthStatusBanner tone="error" message={formError} live /> : null}

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
          className="min-h-jp-button w-full rounded-jp-md border border-jp-border bg-jp-surface px-4 text-jp-sm text-jp-text focus-visible:outline-none focus-visible:shadow-jp-focus"
        />
      </div>

      <PasswordField label="New password" name="password" value={password} onChange={setPassword} disabled={submitting} error={fieldErrors.password} autoComplete="new-password" />
      <PasswordField
        label="Confirm new password"
        name="password_confirmation"
        value={passwordConfirmation}
        onChange={setPasswordConfirmation}
        disabled={submitting}
        error={fieldErrors.password_confirmation}
        autoComplete="new-password"
      />

      <PrimaryButton type="submit" className="w-full" disabled={submitting}>
        {submitting ? "Updating password…" : "Reset password"}
      </PrimaryButton>
    </form>
  );
}
