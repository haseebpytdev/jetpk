"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { fetchRegistrationSecurityQuestion, registerCustomer } from "../services/registration-service";
import { mapFieldErrors } from "../utils/laravel-auth-api";
import { AuthStatusBanner } from "./AuthStatusBanner";
import { PasswordField } from "./PasswordField";

const fieldClass =
  "min-h-jp-button w-full rounded-jp-md border border-jp-border bg-jp-surface px-4 text-jp-sm text-jp-text placeholder:text-jp-muted focus-visible:outline-none focus-visible:shadow-jp-focus";

export function CustomerRegistrationForm() {
  const router = useRouter();
  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [email, setEmail] = useState("");
  const [mobileCountryCode, setMobileCountryCode] = useState("+92");
  const [mobile, setMobile] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [securityAnswer, setSecurityAnswer] = useState("");
  const [securityQuestion, setSecurityQuestion] = useState<string | null>(null);
  const [termsAccepted, setTermsAccepted] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    void fetchRegistrationSecurityQuestion().then(setSecurityQuestion);
  }, []);

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (submitting) return;

    setSubmitting(true);
    setFormError("");
    setFieldErrors({});

    const result = await registerCustomer({
      first_name: firstName.trim(),
      last_name: lastName.trim(),
      email: email.trim().toLowerCase(),
      mobile_country_code: mobileCountryCode,
      mobile,
      password,
      password_confirmation: passwordConfirmation,
      security_answer: securityAnswer,
      terms: termsAccepted ? "1" : "",
    });

    if (!result.ok) {
      setSubmitting(false);
      setFieldErrors(mapFieldErrors(result.fieldErrors));
      setFormError(result.message);
      if (result.fieldErrors?.security_answer) {
        void fetchRegistrationSecurityQuestion().then(setSecurityQuestion);
        setSecurityAnswer("");
      }
      return;
    }

    window.location.assign(result.redirect);
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="space-y-4">
      {formError ? <AuthStatusBanner tone="error" message={formError} live /> : null}

      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="First name" id="first_name" value={firstName} onChange={setFirstName} error={fieldErrors.first_name} disabled={submitting} />
        <Field label="Last name" id="last_name" value={lastName} onChange={setLastName} error={fieldErrors.last_name} disabled={submitting} />
      </div>

      <Field label="Email" id="email" type="email" value={email} onChange={setEmail} error={fieldErrors.email} disabled={submitting} autoComplete="email" />

      <div className="grid gap-4 sm:grid-cols-[7rem_1fr]">
        <Field label="Code" id="mobile_country_code" value={mobileCountryCode} onChange={setMobileCountryCode} error={fieldErrors.mobile_country_code} disabled={submitting} />
        <Field label="Mobile" id="mobile" value={mobile} onChange={setMobile} error={fieldErrors.mobile} disabled={submitting} inputMode="numeric" />
      </div>

      <PasswordField label="Password" name="password" value={password} onChange={setPassword} disabled={submitting} error={fieldErrors.password} autoComplete="new-password" hint="At least 8 characters." />
      <PasswordField label="Confirm password" name="password_confirmation" value={passwordConfirmation} onChange={setPasswordConfirmation} disabled={submitting} error={fieldErrors.password_confirmation} autoComplete="new-password" />

      {securityQuestion ? (
        <Field
          label={securityQuestion}
          id="security_answer"
          value={securityAnswer}
          onChange={setSecurityAnswer}
          error={fieldErrors.security_answer}
          disabled={submitting}
          inputMode="numeric"
        />
      ) : null}

      <label className="flex items-start gap-2 text-jp-sm text-jp-text">
        <input
          type="checkbox"
          checked={termsAccepted}
          disabled={submitting}
          onChange={(event) => setTermsAccepted(event.target.checked)}
          className="mt-1 h-4 w-4 rounded border-jp-border text-jp-primary focus-visible:shadow-jp-focus"
        />
        <span>
          I accept the{" "}
          <a href="/terms" className="font-semibold text-jp-primary hover:underline">
            Terms
          </a>{" "}
          and{" "}
          <a href="/privacy" className="font-semibold text-jp-primary hover:underline">
            Privacy Policy
          </a>
          .
        </span>
      </label>
      {fieldErrors.terms ? <p className="text-jp-xs text-jp-danger">{fieldErrors.terms}</p> : null}

      <PrimaryButton type="submit" className="w-full" disabled={submitting}>
        {submitting ? "Creating account…" : "Create account"}
      </PrimaryButton>

      <p className="text-center text-jp-sm text-jp-muted">
        Already have an account?{" "}
        <a href="/login" className="font-semibold text-jp-primary hover:underline">
          Sign in
        </a>
      </p>
    </form>
  );
}

function Field({
  label,
  id,
  value,
  onChange,
  error,
  disabled,
  type = "text",
  autoComplete,
  inputMode,
}: {
  label: string;
  id: string;
  value: string;
  onChange: (value: string) => void;
  error?: string;
  disabled?: boolean;
  type?: string;
  autoComplete?: string;
  inputMode?: React.HTMLAttributes<HTMLInputElement>["inputMode"];
}) {
  return (
    <div>
      <label htmlFor={id} className="mb-1.5 block text-jp-sm font-medium text-jp-text">
        {label} <span className="text-jp-danger">*</span>
      </label>
      <input
        id={id}
        name={id}
        type={type}
        autoComplete={autoComplete}
        inputMode={inputMode}
        required
        disabled={disabled}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        aria-invalid={error ? true : undefined}
        className={fieldClass}
      />
      {error ? (
        <p role="alert" className="mt-1 text-jp-xs text-jp-danger">
          {error}
        </p>
      ) : null}
    </div>
  );
}
