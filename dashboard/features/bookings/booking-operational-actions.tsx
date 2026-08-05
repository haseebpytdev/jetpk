"use client";

import { useState } from "react";
import { useDashboardPortal } from "@/lib/portal-context";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { storeBookingNote, storeCancellationRequest, storeRefundRequest, storeBookingPayment, assignBookingStaff } from "@/services/operational-api";

export function BookingOperationalActions({ bookingId }: { bookingId: string }) {
  const portal = useDashboardPortal();
  const isLive = useDashboardLiveMode();
  const [note, setNote] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  if (!isLive) {
    return (
      <p className="text-xs text-jp-muted" data-testid="booking-ops-preview">
        Booking operational actions are available in live dashboard mode only.
      </p>
    );
  }

  async function handleAddNote() {
    if (!note.trim()) {
      setError("Note text is required.");
      return;
    }
    setBusy(true);
    setError(null);
    setSuccess(null);
    const result = await storeBookingNote(portal, bookingId, note.trim(), false);
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Request failed");
      return;
    }
    setNote("");
    setSuccess("Internal note added.");
  }

  return (
    <div className="space-y-2" data-testid="booking-operational-actions">
      <h3 className="text-sm font-semibold text-gray-900">Internal note</h3>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {success ? <p className="text-sm text-green-700">{success}</p> : null}
      <textarea
        className="w-full rounded-lg border border-jp-border p-2 text-sm"
        rows={3}
        value={note}
        onChange={(e) => setNote(e.target.value)}
        data-testid="booking-note-input"
      />
      <button
        type="button"
        className="min-h-11 rounded-xl bg-jp-accent px-3 py-2 text-sm text-white disabled:opacity-60"
        disabled={busy}
        onClick={handleAddNote}
        data-testid="booking-note-submit"
      >
        {busy ? "Saving…" : "Add internal note"}
      </button>
      <div className="flex flex-wrap gap-2 pt-2">
        <button
          type="button"
        className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
        disabled={busy}
        onClick={async () => {
          setBusy(true);
          setError(null);
          const result = await storeCancellationRequest(portal, bookingId, {
            cancellation_type: "booking_cancel",
            reason: "Operator requested cancellation",
          });
          setBusy(false);
          if (!result.ok) setError(result.message ?? "Request failed");
          else setSuccess("Cancellation request created.");
        }}
        data-testid="booking-cancellation-store"
      >
        Request cancellation
      </button>
      <button
        type="button"
        className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
        disabled={busy}
        onClick={async () => {
          setBusy(true);
          setError(null);
          const result = await storeRefundRequest(portal, bookingId, {
            amount: 100,
            currency: "PKR",
            method: "cash",
            reason: "Operator refund request",
          });
          setBusy(false);
          if (!result.ok) setError(result.message ?? "Request failed");
          else setSuccess("Refund request created.");
        }}
        data-testid="booking-refund-store"
      >
        Request refund
      </button>
      <button
        type="button"
        className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
        disabled={busy}
        onClick={async () => {
          setBusy(true);
          setError(null);
          const result = await storeBookingPayment(portal, bookingId, {
            method: "bank_transfer",
            amount: 100,
            currency: "PKR",
          });
          setBusy(false);
          if (!result.ok) setError(result.message ?? "Request failed");
          else setSuccess("Payment record created.");
        }}
        data-testid="booking-payment-store"
      >
        Record payment
      </button>
      {portal === "admin" ? (
        <button
          type="button"
          className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
          disabled={busy}
          onClick={async () => {
            setBusy(true);
            setError(null);
            const result = await assignBookingStaff(bookingId, null);
            setBusy(false);
            if (!result.ok) setError(result.message ?? "Request failed");
            else setSuccess("Staff assignment updated.");
          }}
          data-testid="booking-assign-staff"
        >
          Clear staff assignment
        </button>
      ) : null}
      </div>
    </div>
  );
}
