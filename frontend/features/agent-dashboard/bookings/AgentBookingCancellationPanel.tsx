"use client";

import { useState } from "react";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { agentApiErrorMessage, requestAgentBookingCancellation } from "../services/agent-dashboard-api";

type AgentBookingCancellationPanelProps = {
  bookingReference: string;
  canRequest: boolean;
  reasonUnavailable?: string | null;
  onSubmitted: () => void;
};

export function AgentBookingCancellationPanel({
  bookingReference,
  canRequest,
  reasonUnavailable,
  onSubmitted,
}: AgentBookingCancellationPanelProps) {
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState<string | null>(null);

  if (!canRequest) {
    return (
      <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid="agent-cancellation-status">
        <h2 className="text-jp-base font-semibold text-jp-text">Cancellation</h2>
        <p className="mt-2 text-jp-sm text-jp-muted">{reasonUnavailable ?? "Cancellation is not available for this booking."}</p>
      </section>
    );
  }

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (submitting) return;
    setSubmitting(true);
    setError(null);
    setSuccess(null);
    const result = await requestAgentBookingCancellation(bookingReference, {
      reason: reason.trim() || undefined,
      cancellation_type: "booking_cancel",
    });
    if (!result.ok) {
      setError(agentApiErrorMessage(result));
      setSubmitting(false);
      return;
    }
    setSuccess(result.data.message);
    onSubmitted();
    setSubmitting(false);
  };

  return (
    <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid="agent-cancellation-request-panel">
      <h2 className="text-jp-base font-semibold text-jp-text">Request cancellation</h2>
      <p className="mt-2 text-jp-sm text-jp-muted">
        Submitting a request does not cancel the booking immediately. Our team will review it.
      </p>
      {error ? (
        <p className="mt-3 text-jp-sm text-red-700" role="alert">
          {error}
        </p>
      ) : null}
      {success ? (
        <p className="mt-3 text-jp-sm text-green-700" role="status">
          {success}
        </p>
      ) : null}
      <form className="mt-4 space-y-3" onSubmit={handleSubmit}>
        <label className="block text-jp-sm">
          Reason (optional)
          <textarea
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2"
            rows={3}
          />
        </label>
        <PrimaryButton type="submit" disabled={submitting}>
          {submitting ? "Submitting…" : "Submit cancellation request"}
        </PrimaryButton>
      </form>
    </section>
  );
}
