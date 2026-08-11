"use client";

import { useState } from "react";
import { useDashboardPortal } from "@/lib/portal-context";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import {
  storeBookingNote,
  storeCancellationRequest,
  storeRefundRequest,
  storeBookingPayment,
  assignBookingStaff,
} from "@/services/operational-api";

const PAYMENT_METHODS = ["bank_transfer", "cash", "card", "wallet", "office"] as const;

export function BookingOperationalActions({
  bookingId,
  defaultCurrency = "",
}: {
  bookingId: string;
  defaultCurrency?: string;
}) {
  const portal = useDashboardPortal();
  const isLive = useDashboardLiveMode();
  const moneyCurrency = defaultCurrency?.trim() || "";
  const [note, setNote] = useState("");
  const [paymentAmount, setPaymentAmount] = useState("");
  const [paymentMethod, setPaymentMethod] = useState<(typeof PAYMENT_METHODS)[number]>("bank_transfer");
  const [refundAmount, setRefundAmount] = useState("");
  const [refundMethod, setRefundMethod] = useState<(typeof PAYMENT_METHODS)[number]>("bank_transfer");
  const [refundReason, setRefundReason] = useState("");
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

  function parsePositiveAmount(raw: string): number | null {
    const normalized = raw.trim().replace(/,/g, "");
    if (normalized === "") {
      return null;
    }
    const value = Number(normalized);
    if (!Number.isFinite(value) || value <= 0) {
      return null;
    }
    return Math.round(value * 100) / 100;
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

  async function handleRecordPayment() {
    if (!moneyCurrency) {
      setError("Booking currency is required before recording a payment.");
      return;
    }
    const amount = parsePositiveAmount(paymentAmount);
    if (amount === null) {
      setError("Enter a valid payment amount greater than zero.");
      return;
    }
    setBusy(true);
    setError(null);
    setSuccess(null);
    const result = await storeBookingPayment(portal, bookingId, {
      method: paymentMethod,
      amount,
      currency: moneyCurrency,
    });
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Request failed");
      return;
    }
    setPaymentAmount("");
    setSuccess("Payment record created.");
  }

  async function handleRequestRefund() {
    if (!moneyCurrency) {
      setError("Booking currency is required before requesting a refund.");
      return;
    }
    const amount = parsePositiveAmount(refundAmount);
    if (amount === null) {
      setError("Enter a valid refund amount greater than zero.");
      return;
    }
    if (!refundReason.trim()) {
      setError("Refund reason is required.");
      return;
    }
    setBusy(true);
    setError(null);
    setSuccess(null);
    const result = await storeRefundRequest(portal, bookingId, {
      amount,
      currency: moneyCurrency,
      method: refundMethod,
      reason: refundReason.trim(),
    });
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Request failed");
      return;
    }
    setRefundAmount("");
    setRefundReason("");
    setSuccess("Refund request created.");
  }

  return (
    <div className="space-y-4" data-testid="booking-operational-actions">
      <div className="space-y-2">
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
      </div>

      <div className="space-y-2 border-t border-jp-border pt-4">
        <h3 className="text-sm font-semibold text-gray-900">Record payment</h3>
        <div className="grid gap-2 sm:grid-cols-3">
          <label className="block text-xs font-medium text-jp-muted">
            Amount {moneyCurrency ? `(${moneyCurrency})` : ""}
            <input
              type="text"
              inputMode="decimal"
              className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
              value={paymentAmount}
              onChange={(e) => setPaymentAmount(e.target.value)}
              data-testid="booking-payment-amount"
            />
          </label>
          <label className="block text-xs font-medium text-jp-muted">
            Method
            <select
              className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
              value={paymentMethod}
              onChange={(e) => setPaymentMethod(e.target.value as (typeof PAYMENT_METHODS)[number])}
              data-testid="booking-payment-method"
            >
              {PAYMENT_METHODS.map((method) => (
                <option key={method} value={method}>
                  {method.replace(/_/g, " ")}
                </option>
              ))}
            </select>
          </label>
          <div className="flex items-end">
            <button
              type="button"
              className="min-h-11 w-full rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
              disabled={busy}
              onClick={handleRecordPayment}
              data-testid="booking-payment-store"
            >
              Record payment
            </button>
          </div>
        </div>
      </div>

      <div className="space-y-2 border-t border-jp-border pt-4">
        <h3 className="text-sm font-semibold text-gray-900">Request refund</h3>
        <div className="grid gap-2 sm:grid-cols-2">
          <label className="block text-xs font-medium text-jp-muted">
            Amount {moneyCurrency ? `(${moneyCurrency})` : ""}
            <input
              type="text"
              inputMode="decimal"
              className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
              value={refundAmount}
              onChange={(e) => setRefundAmount(e.target.value)}
              data-testid="booking-refund-amount"
            />
          </label>
          <label className="block text-xs font-medium text-jp-muted">
            Method
            <select
              className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
              value={refundMethod}
              onChange={(e) => setRefundMethod(e.target.value as (typeof PAYMENT_METHODS)[number])}
              data-testid="booking-refund-method"
            >
              {PAYMENT_METHODS.map((method) => (
                <option key={method} value={method}>
                  {method.replace(/_/g, " ")}
                </option>
              ))}
            </select>
          </label>
        </div>
        <label className="block text-xs font-medium text-jp-muted">
          Reason
          <input
            type="text"
            className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
            value={refundReason}
            onChange={(e) => setRefundReason(e.target.value)}
            data-testid="booking-refund-reason"
          />
        </label>
        <button
          type="button"
          className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
          disabled={busy}
          onClick={handleRequestRefund}
          data-testid="booking-refund-store"
        >
          Request refund
        </button>
      </div>

      <div className="flex flex-wrap gap-2 border-t border-jp-border pt-4">
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
