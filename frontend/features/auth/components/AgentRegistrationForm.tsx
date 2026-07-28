"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { useState } from "react";
import { registerAgent } from "../services/registration-service";
import { mapFieldErrors } from "../utils/laravel-auth-api";
import { AuthStatusBanner } from "./AuthStatusBanner";

const fieldClass =
  "min-h-jp-button w-full rounded-jp-md border border-jp-border bg-jp-surface px-4 text-jp-sm text-jp-text placeholder:text-jp-muted focus-visible:outline-none focus-visible:shadow-jp-focus";

const BUSINESS_TYPES = [
  { value: "travel_agency", label: "Travel agency" },
  { value: "tour_operator", label: "Tour operator" },
  { value: "corporate_travel", label: "Corporate travel desk" },
  { value: "other", label: "Other" },
];

export function AgentRegistrationForm() {
  const [companyName, setCompanyName] = useState("");
  const [city, setCity] = useState("");
  const [businessType, setBusinessType] = useState("");
  const [firstName, setFirstName] = useState("");
  const [email, setEmail] = useState("");
  const [mobileCountryCode, setMobileCountryCode] = useState("+92");
  const [mobile, setMobile] = useState("");
  const [notes, setNotes] = useState("");
  const [termsAccepted, setTermsAccepted] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [successMessage, setSuccessMessage] = useState("");

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (submitting) return;

    setSubmitting(true);
    setFormError("");
    setFieldErrors({});
    setSuccessMessage("");

    const result = await registerAgent({
      company_name: companyName.trim(),
      city: city.trim(),
      business_type: businessType,
      first_name: firstName.trim(),
      email: email.trim().toLowerCase(),
      mobile_country_code: mobileCountryCode,
      mobile,
      notes: notes.trim(),
      terms: termsAccepted ? "1" : "",
    });

    if (!result.ok) {
      setSubmitting(false);
      setFieldErrors(mapFieldErrors(result.fieldErrors));
      setFormError(result.message);
      return;
    }

    setSuccessMessage(result.message ?? "Your agent application has been submitted and is pending review.");
    window.location.assign(result.redirect);
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="space-y-4">
      {successMessage ? <AuthStatusBanner tone="success" message={successMessage} live /> : null}
      {formError ? <AuthStatusBanner tone="error" message={formError} live /> : null}

      <Field label="Agency name" id="company_name" value={companyName} onChange={setCompanyName} error={fieldErrors.company_name} disabled={submitting} />
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="City" id="city" value={city} onChange={setCity} error={fieldErrors.city} disabled={submitting} />
        <div>
          <label htmlFor="business_type" className="mb-1.5 block text-jp-sm font-medium text-jp-text">
            Business type <span className="text-jp-danger">*</span>
          </label>
          <select
            id="business_type"
            name="business_type"
            required
            disabled={submitting}
            value={businessType}
            onChange={(event) => setBusinessType(event.target.value)}
            className={fieldClass}
          >
            <option value="">Select business type</option>
            {BUSINESS_TYPES.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
          {fieldErrors.business_type ? <p className="mt-1 text-jp-xs text-jp-danger">{fieldErrors.business_type}</p> : null}
        </div>
      </div>

      <Field label="Applicant first name" id="first_name" value={firstName} onChange={setFirstName} error={fieldErrors.first_name} disabled={submitting} />
      <Field label="Email" id="email" type="email" value={email} onChange={setEmail} error={fieldErrors.email} disabled={submitting} />
      <div className="grid gap-4 sm:grid-cols-[7rem_1fr]">
        <Field label="Code" id="mobile_country_code" value={mobileCountryCode} onChange={setMobileCountryCode} error={fieldErrors.mobile_country_code} disabled={submitting} />
        <Field label="Mobile" id="mobile" value={mobile} onChange={setMobile} error={fieldErrors.mobile} disabled={submitting} inputMode="numeric" />
      </div>

      <div>
        <label htmlFor="notes" className="mb-1.5 block text-jp-sm font-medium text-jp-text">
          Additional details
        </label>
        <textarea
          id="notes"
          name="notes"
          rows={4}
          disabled={submitting}
          value={notes}
          onChange={(event) => setNotes(event.target.value)}
          className={`${fieldClass} py-3`}
        />
        {fieldErrors.notes ? <p className="mt-1 text-jp-xs text-jp-danger">{fieldErrors.notes}</p> : null}
      </div>

      <label className="flex items-start gap-2 text-jp-sm text-jp-text">
        <input
          type="checkbox"
          checked={termsAccepted}
          disabled={submitting}
          onChange={(event) => setTermsAccepted(event.target.checked)}
          className="mt-1 h-4 w-4 rounded border-jp-border text-jp-primary focus-visible:shadow-jp-focus"
        />
        <span>I confirm the submitted information is accurate.</span>
      </label>
      {fieldErrors.terms ? <p className="text-jp-xs text-jp-danger">{fieldErrors.terms}</p> : null}

      <PrimaryButton type="submit" className="w-full" disabled={submitting}>
        {submitting ? "Submitting application…" : "Submit application"}
      </PrimaryButton>

      <p className="text-center text-jp-sm text-jp-muted">
        Already registered?{" "}
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
  inputMode,
}: {
  label: string;
  id: string;
  value: string;
  onChange: (value: string) => void;
  error?: string;
  disabled?: boolean;
  type?: string;
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
        inputMode={inputMode}
        required
        disabled={disabled}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className={fieldClass}
      />
      {error ? <p className="mt-1 text-jp-xs text-jp-danger">{error}</p> : null}
    </div>
  );
}
