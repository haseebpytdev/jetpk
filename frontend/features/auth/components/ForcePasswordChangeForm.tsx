"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { useState } from "react";
import { logout } from "../services/auth-service";
import { submitForcePasswordChange } from "../services/force-password-service";
import { markForcePasswordRequirementCleared } from "../utils/force-password-clearance";
import { mapFieldErrors } from "../utils/laravel-auth-api";
import { AuthStatusBanner } from "./AuthStatusBanner";
import { PasswordField } from "./PasswordField";

export function ForcePasswordChangeForm() {
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);
  const [formError, setFormError] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (submitting || loggingOut) return;

    setSubmitting(true);
    setFormError("");
    setFieldErrors({});

    const result = await submitForcePasswordChange({
      password,
      password_confirmation: passwordConfirmation,
    });

    if (!result.ok) {
      setSubmitting(false);
      setFieldErrors(mapFieldErrors(result.fieldErrors));
      setFormError(result.fieldErrors?.password?.[0] ?? result.message);
      return;
    }

    markForcePasswordRequirementCleared();
    window.location.assign(result.redirect);
  }

  async function handleLogout() {
    if (submitting || loggingOut) return;

    setLoggingOut(true);
    setFormError("");

    const result = await logout();
    if (!result.ok) {
      setLoggingOut(false);
      setFormError(result.message);
      return;
    }

    window.location.assign(result.redirect);
  }

  return (
    <div className="space-y-4">
      <form onSubmit={handleSubmit} noValidate className="space-y-4">
        {formError ? <AuthStatusBanner tone="error" message={formError} live /> : null}

        <PasswordField
          label="New password"
          name="password"
          value={password}
          onChange={setPassword}
          disabled={submitting || loggingOut}
          error={fieldErrors.password}
          autoComplete="new-password"
        />
        <PasswordField
          label="Confirm new password"
          name="password_confirmation"
          value={passwordConfirmation}
          onChange={setPasswordConfirmation}
          disabled={submitting || loggingOut}
          error={fieldErrors.password_confirmation}
          autoComplete="new-password"
        />

        <PrimaryButton type="submit" className="w-full" disabled={submitting || loggingOut}>
          {submitting ? "Updating password…" : "Save password"}
        </PrimaryButton>
      </form>

      <div className="text-center">
        <button
          type="button"
          onClick={handleLogout}
          disabled={submitting || loggingOut}
          className="text-jp-sm text-jp-muted underline hover:text-jp-primary focus-visible:outline-none focus-visible:shadow-jp-focus rounded-jp-sm"
        >
          {loggingOut ? "Logging out…" : "Log out instead"}
        </button>
      </div>
    </div>
  );
}
