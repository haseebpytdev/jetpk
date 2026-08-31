"use client";

import type { ContactFormValues } from "../types";

type ContactDetailsSectionProps = {
  contact: ContactFormValues;
  locked: boolean;
  canCreateAccount: boolean;
  fieldErrors: Record<string, string>;
  onChange: (field: keyof ContactFormValues, value: string | boolean) => void;
  accountMatch?: boolean;
  continueAsGuest?: boolean;
  onEmailBlur?: (email: string) => void;
  onSignInContinue?: () => void;
  onContinueAsGuest?: () => void;
};

const fieldClass = "mt-1 h-10 w-full rounded-jp-md border border-jp-border bg-white px-3 text-sm text-jp-text shadow-sm outline-none transition-colors focus:border-jp-primary focus:ring-2 focus:ring-jp-primary/20 disabled:bg-jp-surface-muted aria-[invalid=true]:border-red-600 aria-[invalid=true]:ring-1 aria-[invalid=true]:ring-red-200";

export function ContactDetailsSection({
  contact,
  locked,
  canCreateAccount,
  fieldErrors,
  onChange,
  accountMatch = false,
  continueAsGuest = false,
  onEmailBlur,
  onSignInContinue,
  onContinueAsGuest,
}: ContactDetailsSectionProps) {
  const showMatchBanner = accountMatch && !continueAsGuest && !locked;

  return (
    <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 shadow-jp-card sm:p-5" data-testid="contact-details">
      <div className="border-b border-jp-border-soft pb-3">
      <h2 className="text-base font-semibold text-jp-text">Contact details</h2>
      <p className="mt-1 text-jp-sm text-jp-muted">Booking confirmation will be sent to this contact.</p>
      </div>

      {showMatchBanner ? (
        <div
          className="mt-4 rounded-jp-md border border-jp-border bg-jp-surface-muted p-3"
          data-testid="existing-account-prompt"
          role="status"
        >
          <p className="text-sm font-semibold text-jp-text">You already have a JetPakistan account.</p>
          <p className="mt-1 text-jp-sm text-jp-muted">
            Sign in to continue with this booking, or continue as a guest. Continuing as guest will not link this booking to your account.
          </p>
          <div className="mt-3 flex flex-wrap gap-2">
            <button
              type="button"
              className="rounded-jp-md bg-jp-primary px-3 py-2 text-sm font-semibold text-white"
              data-testid="existing-account-sign-in"
              onClick={onSignInContinue}
            >
              Sign in &amp; continue
            </button>
            <button
              type="button"
              className="rounded-jp-md border border-jp-border bg-white px-3 py-2 text-sm font-semibold text-jp-text"
              data-testid="existing-account-continue-guest"
              onClick={onContinueAsGuest}
            >
              Continue as guest
            </button>
          </div>
        </div>
      ) : null}

      <div className="mt-4 grid gap-x-5 gap-y-4 sm:grid-cols-2">
        <label className="text-jp-sm sm:col-span-2">
          Contact name <span className="text-jp-muted">(optional)</span>
          <input
            type="text"
            disabled={locked}
            value={contact.contact_name}
            onChange={(e) => onChange("contact_name", e.target.value)}
            className={fieldClass}
          />
        </label>

        <label className="text-jp-sm">
          Email <span className="text-red-700">*</span>
          <input
            type="email"
            autoComplete="email"
            disabled={locked}
            value={contact.email}
            onChange={(e) => onChange("email", e.target.value)}
            onBlur={(e) => onEmailBlur?.(e.target.value)}
            className={fieldClass}
            aria-invalid={Boolean(fieldErrors.email)}
          />
          {fieldErrors.email ? <p className="mt-1 text-jp-sm text-red-700">{fieldErrors.email}</p> : null}
        </label>

        <label className="text-jp-sm">
          Mobile <span className="text-red-700">*</span>
          <input
            type="tel"
            autoComplete="tel"
            disabled={locked}
            value={contact.phone}
            onChange={(e) => onChange("phone", e.target.value)}
            className={fieldClass}
            aria-invalid={Boolean(fieldErrors.phone)}
          />
          {fieldErrors.phone ? <p className="mt-1 text-jp-sm text-red-700">{fieldErrors.phone}</p> : null}
        </label>

        {canCreateAccount && !showMatchBanner ? (
          <>
            <label className="flex items-center gap-2 text-jp-sm sm:col-span-2">
              <input
                type="checkbox"
                checked={Boolean(contact.create_account)}
                onChange={(e) => onChange("create_account", e.target.checked)}
              />
              Create a JetPakistan account with this email
            </label>
            {contact.create_account ? (
              <>
                <label className="text-jp-sm">
                  Password <span className="text-red-700">*</span>
                  <input
                    type="password"
                    autoComplete="new-password"
                    value={contact.password ?? ""}
                    onChange={(e) => onChange("password", e.target.value)}
                    className={fieldClass}
                  />
                </label>
                <label className="text-jp-sm">
                  Confirm password <span className="text-red-700">*</span>
                  <input
                    type="password"
                    autoComplete="new-password"
                    value={contact.password_confirmation ?? ""}
                    onChange={(e) => onChange("password_confirmation", e.target.value)}
                    className={fieldClass}
                  />
                </label>
              </>
            ) : null}
          </>
        ) : null}
      </div>
    </section>
  );
}
