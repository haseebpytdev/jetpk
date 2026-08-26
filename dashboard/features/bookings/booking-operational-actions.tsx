"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useDashboardPortal } from "@/lib/portal-context";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import {
  storeBookingNote,
  storeCancellationRequest,
  storeRefundRequest,
  storeBookingPayment,
  assignBookingStaff,
  issueTicketExecution,
  createSupplierBookingAction,
  prepareSupplierPnrContextAction,
  syncPnrItineraryAction,
  updateBookingStatusAction,
  adminDirectCancelBooking,
} from "@/services/operational-api";
import type { BookingOperationalCapabilities, BookingStatus, PaymentStatus } from "@/types/booking";

const PAYMENT_METHODS = ["bank_transfer", "cash", "card", "wallet", "office"] as const;

const STATUS_LABELS: Record<string, string> = {
  draft: "Draft",
  pending: "Pending",
  fare_review: "Fare review",
  confirmed: "Confirmed",
  payment_pending: "Payment pending",
  paid: "Paid",
  ticketing_pending: "Ticketing pending",
  ticketed: "Ticketed",
  cancelled: "Cancelled",
  expired: "Expired",
  failed: "Failed",
  refunded: "Refunded",
};

export function BookingOperationalActions({
  bookingId,
  defaultCurrency = "",
  bookingStatus = "pending",
  paymentStatus = "unpaid",
  amountPaid = 0,
  totalAmount = 0,
  capabilities = null,
}: {
  bookingId: string;
  defaultCurrency?: string;
  bookingStatus?: BookingStatus;
  paymentStatus?: PaymentStatus;
  amountPaid?: number;
  totalAmount?: number;
  capabilities?: BookingOperationalCapabilities | null;
}) {
  const portal = useDashboardPortal();
  const isLive = useDashboardLiveMode();
  const router = useRouter();
  const moneyCurrency = defaultCurrency?.trim() || "";
  const [note, setNote] = useState("");
  const [statusValue, setStatusValue] = useState(bookingStatus);
  const [statusNote, setStatusNote] = useState("");
  const [paymentAmount, setPaymentAmount] = useState("");
  const [paymentMethod, setPaymentMethod] = useState<(typeof PAYMENT_METHODS)[number]>("bank_transfer");
  const [refundAmount, setRefundAmount] = useState("");
  const [refundMethod, setRefundMethod] = useState<(typeof PAYMENT_METHODS)[number]>("bank_transfer");
  const [refundReason, setRefundReason] = useState("");
  const [overrideReason, setOverrideReason] = useState("");
  const [cancelReason, setCancelReason] = useState("");
  const [cancelModalOpen, setCancelModalOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const isCancelled = bookingStatus === "cancelled";
  const isFailed = bookingStatus === "failed";

  const canRecordPayment =
    capabilities?.can_record_payment ??
    (!isCancelled && !isFailed && (paymentStatus === "unpaid" || paymentStatus === "partial" || paymentStatus === "pending"));
  const canRequestRefund =
    capabilities?.can_request_refund ??
    (!isFailed && amountPaid > 0 && (paymentStatus === "paid" || paymentStatus === "partial"));
  const canRequestCancellation = capabilities?.can_request_cancellation ?? (!isCancelled && !isFailed);
  const canUpdateStatus = Boolean(capabilities?.can_update_status);
  const canPreparePnr = Boolean(capabilities?.can_prepare_pnr_context);
  const canGeneratePnr = Boolean(capabilities?.can_generate_pnr);
  const canRetryPnr = Boolean(capabilities?.can_retry_pnr);
  const canSyncPnr = Boolean(capabilities?.can_sync_pnr);
  const canIssueTicket = Boolean(capabilities?.can_issue_ticket);
  const canVoidTicket = Boolean(capabilities?.can_void_ticket);
  const canAdminMarkPaid = Boolean(capabilities?.can_admin_mark_paid);
  const canCancelSupplier = Boolean(capabilities?.can_cancel_supplier_booking);
  const allowedStatuses = capabilities?.allowed_status_values?.length
    ? capabilities.allowed_status_values
    : Object.keys(STATUS_LABELS);
  const outstanding = Math.max(0, totalAmount - amountPaid);

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

  async function afterMutation(message: string) {
    setSuccess(message);
    router.refresh();
  }

  async function handleStatusUpdate() {
    if (!statusValue) {
      setError("Select a booking status.");
      return;
    }
    if (!window.confirm(`Update booking status to ${STATUS_LABELS[statusValue] ?? statusValue}?`)) {
      return;
    }
    setBusy(true);
    setError(null);
    setSuccess(null);
    const result = await updateBookingStatusAction(portal, bookingId, {
      status: statusValue,
      note: statusNote.trim() || undefined,
    });
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? capabilities?.reasons?.can_update_status ?? "Status update failed");
      return;
    }
    setStatusNote("");
    await afterMutation(result.message ?? "Booking status updated.");
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
    await afterMutation("Internal note added.");
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
    await afterMutation("Payment record created.");
  }

  async function handleAdminMarkPaid() {
    if (!moneyCurrency) {
      setError("Booking currency is required.");
      return;
    }
    if (overrideReason.trim().length < 3) {
      setError("Admin override reason is required.");
      return;
    }
    const amount = outstanding > 0 ? outstanding : parsePositiveAmount(paymentAmount);
    if (amount === null || amount <= 0) {
      setError("Outstanding amount required for mark fully paid.");
      return;
    }
    if (!window.confirm(`Mark booking fully paid for ${amount} ${moneyCurrency}?`)) {
      return;
    }
    setBusy(true);
    setError(null);
    setSuccess(null);
    const result = await storeBookingPayment(portal, bookingId, {
      method: paymentMethod,
      amount,
      currency: moneyCurrency,
      admin_override: true,
      verify_now: true,
      notes: `admin_override: ${overrideReason.trim()}`,
      payment_reference: "admin_override",
    });
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Request failed");
      return;
    }
    setOverrideReason("");
    await afterMutation("Admin mark fully paid recorded.");
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
    setBusy(true);
    setError(null);
    setSuccess(null);
    const result = await storeRefundRequest(portal, bookingId, {
      amount,
      currency: moneyCurrency,
      method: refundMethod,
      reason: refundReason.trim() || "Operator refund request",
    });
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Request failed");
      return;
    }
    setRefundAmount("");
    setRefundReason("");
    await afterMutation("Refund request created.");
  }

  async function handlePnrAction(label: string) {
    if (!window.confirm(`${label}? Supplier connection continuity and duplicate-host protection apply.`)) {
      return;
    }
    setBusy(true);
    setError(null);
    setSuccess(null);
    const result = await createSupplierBookingAction(portal, bookingId, {});
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "PNR action failed");
      return;
    }
    await afterMutation(result.message ?? `${label} completed.`);
  }

  async function handlePreparePnrContext() {
    if (!window.confirm("Prepare Sabre supplier PNR pricing context for this booking?")) {
      return;
    }
    setBusy(true);
    setError(null);
    setSuccess(null);
    const result = await prepareSupplierPnrContextAction(portal, bookingId);
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? capabilities?.reasons?.can_prepare_pnr_context ?? "Prepare context failed");
      return;
    }
    await afterMutation(result.message ?? "Supplier PNR context prepared.");
  }

  async function handleSyncPnr() {
    if (!window.confirm("Sync PNR itinerary from Sabre? This retrieves the live itinerary snapshot.")) {
      return;
    }
    setBusy(true);
    setError(null);
    setSuccess(null);
    const result = await syncPnrItineraryAction(portal, bookingId);
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? capabilities?.reasons?.can_sync_pnr ?? "PNR sync failed");
      return;
    }
    await afterMutation(result.message ?? "PNR itinerary synced.");
  }

  async function handleIssueTicket() {
    if (!window.confirm("Issue ticket for this booking? Payment paid does not auto-ticket — this is a manual operator action.")) {
      return;
    }
    setBusy(true);
    setError(null);
    setSuccess(null);
    const result = await issueTicketExecution(portal, bookingId);
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? capabilities?.reasons?.can_issue_ticket ?? "Ticketing blocked");
      return;
    }
    await afterMutation("Ticket issue requested.");
  }

  async function handleAdminDirectCancel() {
    if (cancelReason.trim().length < 3) {
      setError("Cancellation reason is required.");
      return;
    }
    setBusy(true);
    setError(null);
    setSuccess(null);
    const result = await adminDirectCancelBooking(bookingId, {
      reason: cancelReason.trim(),
      cancellation_type: "booking_cancel",
    });
    setBusy(false);
    setCancelModalOpen(false);
    if (!result.ok) {
      setError(result.message ?? "Cancel failed");
      return;
    }
    const state = result.execution_state;
    await afterMutation(
      state === "pending_reconciliation"
        ? "Cancel submitted — pending supplier reconciliation (booking not falsely cancelled)."
        : result.message ?? "Admin direct cancel completed.",
    );
  }

  const cancelCtx = capabilities?.cancel_pnr_context;

  return (
    <div className="space-y-4" data-testid="booking-operational-actions">
      {error ? (
        <p className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" data-testid="booking-ops-error">
          {error}
        </p>
      ) : null}
      {success ? (
        <p className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900" data-testid="booking-ops-success">
          {success}
        </p>
      ) : null}

      <div className="space-y-2 rounded-xl border border-jp-border p-3" data-testid="booking-ops-status">
        <h3 className="text-xs font-semibold uppercase tracking-wide text-jp-muted">Booking status</h3>
        {canUpdateStatus ? (
          <>
            <label className="block text-xs font-medium text-jp-muted">
              Status
              <select
                className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
                value={statusValue}
                onChange={(e) => setStatusValue(e.target.value as BookingStatus)}
                data-testid="booking-status-select"
              >
                {allowedStatuses.map((value) => (
                  <option key={value} value={value}>
                    {STATUS_LABELS[value] ?? value}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-xs font-medium text-jp-muted">
              Note (optional)
              <input
                type="text"
                className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
                value={statusNote}
                onChange={(e) => setStatusNote(e.target.value)}
                data-testid="booking-status-note"
              />
            </label>
            <button
              type="button"
              className="min-h-11 w-full rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
              disabled={busy || statusValue === bookingStatus}
              onClick={() => void handleStatusUpdate()}
              data-testid="booking-status-update"
            >
              Update status
            </button>
          </>
        ) : (
          <p className="text-xs text-jp-muted" data-testid="booking-status-ineligible">
            {capabilities?.reasons?.can_update_status || "Status update not available."}
          </p>
        )}
      </div>

      <div className="space-y-2 rounded-xl border border-jp-border p-3" data-testid="booking-ops-pnr">
        <h3 className="text-xs font-semibold uppercase tracking-wide text-jp-muted">Supplier / PNR</h3>
        {canPreparePnr ? (
          <button
            type="button"
            className="min-h-11 w-full rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
            disabled={busy}
            onClick={() => void handlePreparePnrContext()}
            data-testid="booking-prepare-pnr-context"
          >
            Prepare supplier PNR context
          </button>
        ) : (
          <p className="text-xs text-jp-muted" data-testid="booking-prepare-pnr-ineligible">
            {capabilities?.reasons?.can_prepare_pnr_context || "Prepare PNR context not available."}
          </p>
        )}
        {canGeneratePnr || canRetryPnr ? (
          <button
            type="button"
            className="min-h-11 w-full rounded-xl bg-jp-green px-3 py-2 text-sm font-medium text-white disabled:opacity-60"
            disabled={busy}
            onClick={() => void handlePnrAction(canRetryPnr && !canGeneratePnr ? "Retry PNR" : "Generate PNR")}
            data-testid="booking-pnr-action"
          >
            {canRetryPnr && !canGeneratePnr ? "Retry PNR" : "Generate PNR"}
          </button>
        ) : (
          <p className="text-xs text-jp-muted" data-testid="booking-pnr-ineligible">
            {capabilities?.reasons?.can_generate_pnr || capabilities?.reasons?.can_retry_pnr || "PNR actions not available."}
          </p>
        )}
        {canSyncPnr ? (
          <button
            type="button"
            className="min-h-11 w-full rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
            disabled={busy}
            onClick={() => void handleSyncPnr()}
            data-testid="booking-sync-pnr"
          >
            Sync PNR itinerary
          </button>
        ) : (
          <p className="text-xs text-jp-muted" data-testid="booking-sync-pnr-ineligible">
            {capabilities?.reasons?.can_sync_pnr || "PNR itinerary sync not available."}
          </p>
        )}
      </div>

      <div className="space-y-2 rounded-xl border border-jp-border p-3" data-testid="booking-ops-ticketing">
        <h3 className="text-xs font-semibold uppercase tracking-wide text-jp-muted">Ticketing</h3>
        {canIssueTicket ? (
          <button
            type="button"
            className="min-h-11 w-full rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
            disabled={busy}
            onClick={() => void handleIssueTicket()}
            data-testid="booking-issue-ticket"
          >
            Issue Ticket
          </button>
        ) : (
          <p className="text-xs text-jp-muted" data-testid="booking-issue-ticket-ineligible">
            {capabilities?.reasons?.can_issue_ticket || "Issue Ticket not eligible."}
          </p>
        )}
        {canVoidTicket ? (
          <button
            type="button"
            className="min-h-11 w-full rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900 disabled:opacity-60"
            disabled={busy}
            onClick={() => {
              setError(
                "Live Sabre void is capability-gated. Confirm SABRE_VOID_LIVE_CALL_ENABLED and use the authorized Laravel void path — Dashboard does not enable production void gates.",
              );
            }}
            data-testid="booking-void-ticket"
          >
            Void ticket
          </button>
        ) : (
          <p className="text-xs text-jp-muted" data-testid="booking-void-ticket-ineligible">
            {capabilities?.reasons?.can_void_ticket || "Void ticket not available."}
            {capabilities?.sabre_void_support ? ` · Sabre void: ${capabilities.sabre_void_support}` : null}
          </p>
        )}
      </div>

      <label className="block text-xs font-medium text-jp-muted">
        Internal note
        <textarea
          className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
          rows={3}
          value={note}
          onChange={(e) => setNote(e.target.value)}
          data-testid="booking-note-input"
        />
      </label>
      <button
        type="button"
        className="min-h-11 rounded-xl bg-jp-green px-3 py-2 text-sm font-medium text-white disabled:opacity-60"
        disabled={busy}
        onClick={handleAddNote}
        data-testid="booking-note-store"
      >
        Add note
      </button>

      {canRecordPayment ? (
        <div className="space-y-2 border-t border-jp-border pt-4" data-testid="booking-payment-panel">
          <h3 className="text-xs font-semibold uppercase tracking-wide text-jp-muted">Payment</h3>
          <div className="grid gap-2 sm:grid-cols-2">
            <label className="block text-xs font-medium text-jp-muted">
              Amount
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
          </div>
          <button
            type="button"
            className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
            disabled={busy}
            onClick={handleRecordPayment}
            data-testid="booking-payment-store"
          >
            Record payment
          </button>
          {canAdminMarkPaid ? (
            <div className="space-y-2 rounded-lg border border-amber-200 bg-amber-50 p-3" data-testid="booking-admin-mark-paid">
              <label className="block text-xs font-medium text-amber-950">
                Mark fully paid — reason
                <input
                  type="text"
                  className="mt-1 w-full rounded-lg border border-amber-300 bg-white p-2 text-sm"
                  value={overrideReason}
                  onChange={(e) => setOverrideReason(e.target.value)}
                  data-testid="booking-admin-mark-paid-reason"
                />
              </label>
              <button
                type="button"
                className="min-h-11 w-full rounded-xl bg-amber-700 px-3 py-2 text-sm font-medium text-white disabled:opacity-60"
                disabled={busy}
                onClick={() => void handleAdminMarkPaid()}
                data-testid="booking-admin-mark-paid-submit"
              >
                Mark Fully Paid — Admin Override
              </button>
            </div>
          ) : null}
        </div>
      ) : null}

      {canRequestRefund ? (
        <div className="space-y-2 border-t border-jp-border pt-4" data-testid="booking-refund-panel">
          <h3 className="text-xs font-semibold uppercase tracking-wide text-jp-muted">Refund</h3>
          <div className="grid gap-2 sm:grid-cols-2">
            <label className="block text-xs font-medium text-jp-muted">
              Amount
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
      ) : null}

      <div className="space-y-2 border-t border-jp-border pt-4">
        {canRequestCancellation ? (
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
              else await afterMutation("Cancellation request created.");
            }}
            data-testid="booking-cancellation-store"
          >
            Request cancellation
          </button>
        ) : (
          <p className="text-xs text-jp-muted" data-testid="booking-cancellation-ineligible">
            Cancellation request is not available for {bookingStatus} bookings.
          </p>
        )}

        {canCancelSupplier ? (
          <div className="space-y-2 rounded-lg border border-red-200 bg-red-50 p-3" data-testid="booking-admin-direct-cancel">
            {cancelCtx?.environment_is_sandbox ? (
              <p
                className="rounded-md border border-amber-300 bg-amber-50 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-amber-900"
                data-testid="booking-admin-direct-cancel-sandbox-badge"
              >
                {cancelCtx.environment_label ?? "TEST / SANDBOX"}
              </p>
            ) : null}
            <label className="block text-xs font-medium text-red-950">
              Cancel PNR — reason
              <input
                type="text"
                className="mt-1 w-full rounded-lg border border-red-300 bg-white p-2 text-sm"
                value={cancelReason}
                onChange={(e) => setCancelReason(e.target.value)}
                data-testid="booking-admin-direct-cancel-reason"
              />
            </label>
            <button
              type="button"
              className="min-h-11 w-full rounded-xl bg-red-700 px-3 py-2 text-sm font-medium text-white disabled:opacity-60"
              disabled={busy}
              onClick={() => {
                if (cancelReason.trim().length < 3) {
                  setError("Cancellation reason is required.");
                  return;
                }
                setError(null);
                setCancelModalOpen(true);
              }}
              data-testid="booking-admin-direct-cancel-submit"
            >
              Cancel PNR
            </button>
          </div>
        ) : null}

        {cancelModalOpen ? (
          <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="cancel-pnr-modal-title"
            data-testid="booking-admin-direct-cancel-modal"
          >
            <div className="w-full max-w-md rounded-2xl border border-jp-border bg-white p-5 shadow-lg">
              <h3 id="cancel-pnr-modal-title" className="text-base font-semibold text-jp-ink">
                Cancel PNR?
              </h3>
              <p className="mt-2 text-sm text-jp-muted">This will cancel the airline reservation.</p>
              {cancelCtx?.environment_is_sandbox ? (
                <p className="mt-2 rounded-md border border-amber-300 bg-amber-50 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-amber-900">
                  {cancelCtx.environment_label ?? "TEST / SANDBOX"}
                </p>
              ) : null}
              <dl className="mt-3 space-y-1 text-sm text-jp-ink">
                <div className="flex justify-between gap-3">
                  <dt className="text-jp-muted">Booking</dt>
                  <dd>{cancelCtx?.booking_reference_safe ?? bookingId}</dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt className="text-jp-muted">Supplier</dt>
                  <dd>{cancelCtx?.supplier ?? "Sabre"}</dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt className="text-jp-muted">Environment</dt>
                  <dd>{cancelCtx?.environment_label ?? cancelCtx?.environment ?? "Unknown"}</dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt className="text-jp-muted">Payment</dt>
                  <dd>{cancelCtx?.payment_label ?? "Unpaid"}</dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt className="text-jp-muted">Ticket</dt>
                  <dd>{cancelCtx?.ticket_label ?? "Not issued"}</dd>
                </div>
              </dl>
              <div className="mt-4 flex flex-col gap-2 sm:flex-row-reverse">
                <button
                  type="button"
                  className="min-h-11 rounded-xl bg-red-700 px-3 py-2 text-sm font-medium text-white disabled:opacity-60"
                  disabled={busy}
                  onClick={() => void handleAdminDirectCancel()}
                  data-testid="booking-admin-direct-cancel-confirm"
                >
                  Cancel PNR
                </button>
                <button
                  type="button"
                  className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm disabled:opacity-60"
                  disabled={busy}
                  onClick={() => setCancelModalOpen(false)}
                  data-testid="booking-admin-direct-cancel-back"
                >
                  Go Back
                </button>
              </div>
            </div>
          </div>
        ) : null}

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
              else await afterMutation("Staff assignment updated.");
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
