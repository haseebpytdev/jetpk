"use client";

import { useState } from "react";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { guestApiErrorMessage, submitGuestCancellation } from "../services/guest-booking-api";

type GuestCancellationPanelProps = {
  canRequest: boolean;
  summary: {
    state: string;
    label: string;
    message: string;
    request?: { status: string; status_label: string } | null;
  };
  submitUrl: string | null;
  onSubmitted: () => void;
};

export function GuestCancellationPanel({
  canRequest,
  summary,
  submitUrl,
  onSubmitted,
}: GuestCancellationPanelProps) {
  const [reason, setReason] = useState("");
  const [termsAcknowledged, setTermsAcknowledged] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState<string | null>(null);

  if (summary.state !== "available" || !canRequest || !submitUrl) {
    return (
      <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid="guest-cancellation-status">
        <h2 className="text-jp-base font-semibold text-jp-text">Cancellation</h2>
        <p className="mt-2 text-jp-sm text-jp-muted">{summary.message}</p>
      </section>
    );
  }

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (submitting || !termsAcknowledged) return;
    setSubmitting(true);
    setError(null);
    setSuccess(null);

    const result = await submitGuestCancellation(submitUrl, {
      reason: reason.trim() || undefined,
      cancellation_type: "booking_cancel",
      terms_acknowledged: termsAcknowledged,
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
    <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid="guest-cancellation-panel">
      <h2 className="text-jp-base font-semibold text-jp-text">Request cancellation</h2>
      <p className="mt-2 text-jp-sm text-jp-muted">{summary.message}</p>
      {error ? <p className="mt-3 text-jp-sm text-red-700" role="alert">{error}</p> : null}
      {success ? (
        <p className="mt-3 text-jp-sm text-green-700" role="status">{success}</p>
      ) : (
        <form onSubmit={handleSubmit} className="mt-4 space-y-4">
          <label className="block text-jp-sm">
            Reason (optional)
            <textarea
              value={reason}
              onChange={(event) => setReason(event.target.value)}
              rows={3}
              className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2 focus-visible:outline-none focus-visible:shadow-jp-focus"
            />
          </label>
          <label className="flex items-start gap-2 text-jp-sm">
            <input
              type="checkbox"
              checked={termsAcknowledged}
              onChange={(event) => setTermsAcknowledged(event.target.checked)}
              className="mt-1"
              required
            />
            <span>I understand that cancellation is subject to airline fare rules.</span>
          </label>
          <PrimaryButton type="submit" disabled={submitting || !termsAcknowledged} data-testid="guest-submit-cancellation">
            {submitting ? "Submitting…" : "Submit cancellation request"}
          </PrimaryButton>
        </form>
      )}
    </section>
  );
}
