"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import {
  TurnstileUnavailableState,
  TurnstileValidationMessage,
  TurnstileWidget,
  useTurnstileToken,
} from "@/features/security/turnstile";
import { useMemo, useRef, useState } from "react";
import type { ContactFormPayload } from "../types";
import { submitSupportOrContactForm } from "../services/contact-service";

type ContactFormProps = {
  formType?: "contact" | "support";
  showBookingReference?: boolean;
  showCategory?: boolean;
  categories?: Array<{ value: string; label: string }>;
};

const SUPPORT_RECOVERY_PATH = "/support";

const fieldClass =
  "min-h-jp-button w-full rounded-jp-md border border-jp-border bg-jp-surface px-4 text-jp-sm text-jp-text placeholder:text-jp-muted focus-visible:outline-none focus-visible:shadow-jp-focus";

export function ContactForm({
  formType = "contact",
  showBookingReference = false,
  showCategory = false,
  categories = [],
}: ContactFormProps) {
  const firstInvalidRef = useRef<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement | null>(null);
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [subject, setSubject] = useState("");
  const [category, setCategory] = useState("");
  const [bookingReference, setBookingReference] = useState("");
  const [message, setMessage] = useState("");
  const [status, setStatus] = useState<"idle" | "submitting" | "success" | "error">("idle");
  const [ticketReference, setTicketReference] = useState("");
  const [errorMessage, setErrorMessage] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  const {
    config: turnstileConfig,
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

  const turnstileEnabled = tokenRequired && Boolean(turnstileConfig?.site_key);
  const submitDisabled =
    status === "submitting" ||
    turnstileLoading ||
    (turnstileEnabled && !token) ||
    tokenExpired ||
    tokenError;

  const consentCopy = useMemo(
    () =>
      formType === "contact"
        ? "By submitting this form you consent to JetPakistan contacting you about your inquiry."
        : "By submitting this form you consent to JetPakistan processing your support request.",
    [formType],
  );

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (submitDisabled) return;

    setStatus("submitting");
    setErrorMessage("");
    setFieldErrors({});
    firstInvalidRef.current = null;

    const payload: ContactFormPayload = {
      form_type: formType,
      name: name || undefined,
      email,
      subject: subject || undefined,
      category: category || undefined,
      body: message,
      booking_reference: bookingReference || undefined,
      ...(turnstileEnabled && token ? { "cf-turnstile-response": token } : {}),
    };

    const result = await submitSupportOrContactForm(payload);

    if (result.ok) {
      setStatus("success");
      setTicketReference(result.ticket_reference);
      return;
    }

    setStatus("error");
    setErrorMessage(result.message);
    if (result.fieldErrors) {
      const mapped: Record<string, string> = {};
      Object.entries(result.fieldErrors).forEach(([key, messages]) => {
        mapped[key] = messages[0] ?? "Invalid value";
      });
      setFieldErrors(mapped);

      const turnstileField = turnstileConfig?.response_field ?? "cf-turnstile-response";
      if (mapped[turnstileField]) {
        resetToken();
      }
    }
  }

  if (status === "success") {
    return (
      <div className="rounded-jp-lg border border-jp-border bg-jp-primary-soft p-jp-xl" role="status">
        <h2 className="text-jp-md font-semibold text-jp-text">Request received</h2>
        <p className="mt-2 text-jp-sm text-jp-muted">
          Thank you. Your reference is <strong>{ticketReference}</strong>. Our team will respond shortly.
        </p>
      </div>
    );
  }

  if (scriptFailed) {
    return (
      <TurnstileUnavailableState
        recoveryHref={SUPPORT_RECOVERY_PATH}
        title="Security check unavailable"
        body="We could not load the security verification widget. Refresh this page to try again, or contact support if the problem continues."
        linkLabel="Open support"
        onRetry={() => window.location.reload()}
      />
    );
  }

  return (
    <form className="space-y-4" onSubmit={handleSubmit} noValidate data-testid={`${formType}-form`}>
      {errorMessage ? (
        <div
          className="rounded-jp-md border border-red-200 bg-red-50 p-3 text-jp-sm text-red-800"
          role="alert"
          aria-live="polite"
        >
          {errorMessage}
        </div>
      ) : null}

      <div className="sr-only" aria-hidden="true">
        <label htmlFor={`${formType}-website`}>Website</label>
        <input id={`${formType}-website`} name="website" type="text" tabIndex={-1} autoComplete="off" />
      </div>

      <div>
        <label htmlFor={`${formType}-name`} className="text-jp-sm font-medium text-jp-text">
          Your name
        </label>
        <input
          id={`${formType}-name`}
          name="name"
          type="text"
          autoComplete="name"
          className={`${fieldClass} mt-1 ${fieldErrors.name ? "border-red-400" : ""}`}
          value={name}
          onChange={(event) => setName(event.target.value)}
          required
          ref={(element) => {
            if (fieldErrors.name && !firstInvalidRef.current) firstInvalidRef.current = element;
          }}
        />
        {fieldErrors.name ? (
          <p className="mt-1 text-jp-xs text-red-700" id={`${formType}-name-error`}>
            {fieldErrors.name}
          </p>
        ) : null}
      </div>

      <div>
        <label htmlFor={`${formType}-email`} className="text-jp-sm font-medium text-jp-text">
          Email
        </label>
        <input
          id={`${formType}-email`}
          name="email"
          type="email"
          autoComplete="email"
          className={`${fieldClass} mt-1 ${fieldErrors.email ? "border-red-400" : ""}`}
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          required
          aria-describedby={fieldErrors.email ? `${formType}-email-error` : undefined}
          ref={(element) => {
            if (fieldErrors.email && !firstInvalidRef.current) firstInvalidRef.current = element;
          }}
        />
        {fieldErrors.email ? (
          <p className="mt-1 text-jp-xs text-red-700" id={`${formType}-email-error`}>
            {fieldErrors.email}
          </p>
        ) : null}
      </div>

      {formType === "support" ? (
        <div>
          <label htmlFor={`${formType}-subject`} className="text-jp-sm font-medium text-jp-text">
            Subject
          </label>
          <input
            id={`${formType}-subject`}
            name="subject"
            type="text"
            maxLength={200}
            className={`${fieldClass} mt-1 ${fieldErrors.subject ? "border-red-400" : ""}`}
            value={subject}
            onChange={(event) => setSubject(event.target.value)}
            required
          />
          {fieldErrors.subject ? <p className="mt-1 text-jp-xs text-red-700">{fieldErrors.subject}</p> : null}
        </div>
      ) : null}

      {showBookingReference ? (
        <div>
          <label htmlFor={`${formType}-booking-reference`} className="text-jp-sm font-medium text-jp-text">
            Booking reference (optional)
          </label>
          <input
            id={`${formType}-booking-reference`}
            name="booking_reference"
            type="text"
            maxLength={64}
            className={`${fieldClass} mt-1`}
            value={bookingReference}
            onChange={(event) => setBookingReference(event.target.value)}
          />
        </div>
      ) : null}

      {showCategory ? (
        <div>
          <label htmlFor={`${formType}-category`} className="text-jp-sm font-medium text-jp-text">
            Issue type
          </label>
          <select
            id={`${formType}-category`}
            name="category"
            className={`${fieldClass} mt-1 ${fieldErrors.category ? "border-red-400" : ""}`}
            value={category}
            onChange={(event) => setCategory(event.target.value)}
            required
          >
            <option value="" disabled>
              Select issue type
            </option>
            {categories.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
          {fieldErrors.category ? <p className="mt-1 text-jp-xs text-red-700">{fieldErrors.category}</p> : null}
        </div>
      ) : null}

      <div>
        <label htmlFor={`${formType}-message`} className="text-jp-sm font-medium text-jp-text">
          Message
        </label>
        <textarea
          id={`${formType}-message`}
          name="body"
          rows={5}
          maxLength={5000}
          className={`${fieldClass} mt-1 min-h-[8rem] py-3 ${fieldErrors.body ? "border-red-400" : ""}`}
          value={message}
          onChange={(event) => setMessage(event.target.value)}
          required
          aria-describedby={fieldErrors.body ? `${formType}-message-error` : undefined}
          ref={(element) => {
            if (fieldErrors.body && !firstInvalidRef.current) firstInvalidRef.current = element;
          }}
        />
        {fieldErrors.body ? (
          <p className="mt-1 text-jp-xs text-red-700" id={`${formType}-message-error`}>
            {fieldErrors.body}
          </p>
        ) : null}
      </div>

      {turnstileEnabled && turnstileConfig?.site_key ? (
        <div className="space-y-2">
          <TurnstileWidget
            siteKey={turnstileConfig.site_key}
            onToken={setToken}
            onExpire={markExpired}
            onError={markError}
            onScriptError={markScriptFailed}
            resetSignal={resetSignal}
          />
          {tokenExpired ? (
            <TurnstileValidationMessage message="Security check expired. Please complete it again." />
          ) : null}
          {tokenError ? <TurnstileValidationMessage message="Security check failed. Please try again." /> : null}
          {turnstileEnabled && !token && !tokenExpired && !tokenError ? (
            <TurnstileValidationMessage message="Complete the security check before submitting." />
          ) : null}
        </div>
      ) : null}

      <p className="text-jp-xs text-jp-muted">{consentCopy}</p>

      <PrimaryButton
        type="submit"
        disabled={submitDisabled}
        className="w-full sm:w-auto"
        aria-describedby={turnstileEnabled && !token ? `${formType}-turnstile-hint` : undefined}
      >
        {status === "submitting"
          ? "Submitting…"
          : turnstileEnabled && !token && !tokenExpired && !tokenError
            ? "Complete security check to submit"
            : formType === "contact"
              ? "Send message"
              : "Submit support request"}
      </PrimaryButton>
      {turnstileEnabled && !token && !tokenExpired && !tokenError ? (
        <p id={`${formType}-turnstile-hint`} className="text-jp-xs text-jp-muted">
          The submit button stays unavailable until the security check is complete.
        </p>
      ) : null}
    </form>
  );
}
