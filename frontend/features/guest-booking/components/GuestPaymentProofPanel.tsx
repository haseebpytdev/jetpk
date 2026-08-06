"use client";

import { useState } from "react";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { guestApiErrorMessage, submitGuestPaymentProof } from "../services/guest-booking-api";

type GuestPaymentProofPanelProps = {
  submitUrl: string;
  balanceDue: number;
  currency: string;
  onSubmitted: () => void;
};

const METHODS = ["bank_transfer", "cash", "card_manual", "easypaisa", "jazzcash", "other"] as const;

export function GuestPaymentProofPanel({ submitUrl, balanceDue, currency, onSubmitted }: GuestPaymentProofPanelProps) {
  const [method, setMethod] = useState<string>("bank_transfer");
  const [amount, setAmount] = useState(String(balanceDue > 0 ? balanceDue : ""));
  const [reference, setReference] = useState("");
  const [notes, setNotes] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState<string | null>(null);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (submitting) return;
    setSubmitting(true);
    setError(null);
    setSuccess(null);

    const result = await submitGuestPaymentProof(submitUrl, {
      method,
      amount,
      payment_reference: reference.trim() || undefined,
      notes: notes.trim() || undefined,
    });

    if (!result.ok) {
      setError(guestApiErrorMessage(result));
      setSubmitting(false);
      return;
    }

    setSuccess(result.data.message);
    onSubmitted();
    setSubmitting(false);
  };

  return (
    <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid="guest-payment-proof-panel">
      <h2 className="text-jp-base font-semibold text-jp-text">Submit payment proof</h2>
      <p className="mt-2 text-jp-sm text-jp-muted">
        Upload bank transfer or wallet proof so our team can verify your payment.
      </p>
      {error ? <p className="mt-3 text-jp-sm text-red-700" role="alert">{error}</p> : null}
      {success ? (
        <p className="mt-3 text-jp-sm text-green-700" role="status">{success}</p>
      ) : (
        <form onSubmit={handleSubmit} className="mt-4 space-y-3">
          <label className="block text-jp-sm">
            Method
            <select
              value={method}
              onChange={(event) => setMethod(event.target.value)}
              className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2"
            >
              {METHODS.map((value) => (
                <option key={value} value={value}>{value.replace(/_/g, " ")}</option>
              ))}
            </select>
          </label>
          <label className="block text-jp-sm">
            Amount ({currency})
            <input
              type="number"
              step="0.01"
              min="1"
              required
              value={amount}
              onChange={(event) => setAmount(event.target.value)}
              className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2"
            />
          </label>
          <label className="block text-jp-sm">
            Reference (optional)
            <input
              value={reference}
              onChange={(event) => setReference(event.target.value)}
              className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2"
            />
          </label>
          <label className="block text-jp-sm">
            Notes (optional)
            <textarea
              value={notes}
              onChange={(event) => setNotes(event.target.value)}
              rows={2}
              className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2"
            />
          </label>
          <PrimaryButton type="submit" disabled={submitting} data-testid="guest-submit-payment-proof">
            {submitting ? "Submitting…" : "Submit payment proof"}
          </PrimaryButton>
        </form>
      )}
    </section>
  );
}
