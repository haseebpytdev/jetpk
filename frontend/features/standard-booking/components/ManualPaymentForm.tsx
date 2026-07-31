"use client";

import type { CheckoutState } from "../types/review-payment";

type ManualPaymentFormProps = {
  state: CheckoutState;
  error?: string | null;
};

/** Operational manual-payment content only — no shell, progress, or order summary. */
export function ManualPaymentForm({ state, error }: ManualPaymentFormProps) {
  const manual = state.manual_payment;

  return (
    <div className="rounded-jp-lg border border-jp-border bg-jp-surface-muted p-jp-lg" data-testid="manual-payment-form">
      <h2 className="font-display text-jp-heading-sm font-bold text-jp-text">Manual payment instructions</h2>
      <p className="mt-3 text-2xl font-semibold text-jp-text" data-testid="manual-amount-due">
        {manual?.formatted_amount ?? state.pricing.formatted_total}
      </p>
      <ul className="mt-4 list-disc space-y-2 pl-5 text-jp-sm text-jp-muted">
        {(manual?.instructions ?? ["Complete your bank transfer using the reference on your invoice."]).map((line) => (
          <li key={line}>{line}</li>
        ))}
      </ul>
      <p className="mt-4 text-jp-sm">
        Payment status: <strong>{manual?.payment_status_label ?? state.payment_status.label}</strong>
      </p>
      <p className="mt-1 text-jp-sm">
        Booking status: <strong>{state.booking_status.label}</strong>
      </p>
      {error ? <p className="mt-4 text-jp-sm text-jp-danger" role="alert">{error}</p> : null}
    </div>
  );
}
