"use client";

import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { useState } from "react";
import { submitMulticityInquiry } from "../services/flight-results-api";
import { AuthStatusBanner } from "@/features/auth/components/AuthStatusBanner";

type MulticityInquiryActionsProps = {
  searchId: string;
  offerId: string;
  notice?: string | null;
  inquiryUrl?: string | null;
  isAuthenticated?: boolean;
};

export function MulticityInquiryActions({
  searchId,
  offerId,
  notice,
  inquiryUrl,
  isAuthenticated = false,
}: MulticityInquiryActionsProps) {
  const [requesterName, setRequesterName] = useState("");
  const [requesterEmail, setRequesterEmail] = useState("");
  const [notes, setNotes] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");

  if (!inquiryUrl) {
    return notice ? <p className="text-sm text-jp-text-muted">{notice}</p> : null;
  }

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (submitting) return;

    setSubmitting(true);
    setError("");

    try {
      await submitMulticityInquiry({
        searchId,
        offerId,
        requesterName: isAuthenticated ? undefined : requesterName.trim(),
        requesterEmail: isAuthenticated ? undefined : requesterEmail.trim(),
        notes: notes.trim() || undefined,
      });
    } catch {
      setSubmitting(false);
      setError("Unable to submit inquiry. Please try again.");
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-3" data-testid="multicity-inquiry-form">
      {notice ? <p className="text-sm text-jp-text-muted">{notice}</p> : null}
      {error ? <AuthStatusBanner tone="error" message={error} live /> : null}

      {!isAuthenticated ? (
        <div className="grid gap-3 sm:grid-cols-2">
          <div>
            <label htmlFor="inquiry-requester-name" className="mb-1 block text-sm font-medium text-jp-text">
              Your name <span className="text-jp-danger">*</span>
            </label>
            <input
              id="inquiry-requester-name"
              name="requester_name"
              type="text"
              required
              disabled={submitting}
              value={requesterName}
              onChange={(event) => setRequesterName(event.target.value)}
              className="min-h-jp-button w-full rounded-jp-md border border-jp-border bg-jp-surface px-3 text-sm focus-visible:outline-none focus-visible:shadow-jp-focus"
            />
          </div>
          <div>
            <label htmlFor="inquiry-requester-email" className="mb-1 block text-sm font-medium text-jp-text">
              Email <span className="text-jp-danger">*</span>
            </label>
            <input
              id="inquiry-requester-email"
              name="requester_email"
              type="email"
              required
              disabled={submitting}
              value={requesterEmail}
              onChange={(event) => setRequesterEmail(event.target.value)}
              className="min-h-jp-button w-full rounded-jp-md border border-jp-border bg-jp-surface px-3 text-sm focus-visible:outline-none focus-visible:shadow-jp-focus"
            />
          </div>
        </div>
      ) : null}

      <div>
        <label htmlFor="inquiry-notes" className="mb-1 block text-sm font-medium text-jp-text">
          Notes (optional)
        </label>
        <textarea
          id="inquiry-notes"
          name="notes"
          rows={3}
          disabled={submitting}
          value={notes}
          onChange={(event) => setNotes(event.target.value)}
          className="w-full rounded-jp-md border border-jp-border bg-jp-surface px-3 py-2 text-sm focus-visible:outline-none focus-visible:shadow-jp-focus"
        />
      </div>

      <PrimaryButton type="submit" disabled={submitting}>
        {submitting ? "Submitting inquiry…" : "Request booking inquiry"}
      </PrimaryButton>
    </form>
  );
}
