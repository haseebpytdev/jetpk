"use client";

import { useState } from "react";
import { useDashboardPortal } from "@/lib/portal-context";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import type { BookingDetail } from "@/types/booking";
import { EmptyState } from "@/components/ui/empty-state";
import {
  assignBookingStaff,
  storeBookingNote,
  storeBookingPayment,
  storeCancellationRequest,
  storeRefundRequest,
} from "@/services/operational-api";
import { BookingOperationalActions } from "@/features/bookings/booking-operational-actions";

const CANCELLATION_TYPES = [
  { value: "booking_cancel", label: "Booking cancel" },
  { value: "ticket_void", label: "Ticket void" },
  { value: "ticket_refund", label: "Ticket refund" },
  { value: "supplier_cancel", label: "Supplier cancel" },
];

const REFUND_METHODS = [
  "bank_transfer",
  "cash",
  "card_manual",
  "easypaisa",
  "jazzcash",
  "other",
] as const;

const PAYMENT_METHODS = [
  "bank_transfer",
  "cash",
  "card_manual",
  "easypaisa",
  "jazzcash",
  "abhipay",
  "other",
] as const;

export function BookingManagementOperationalSections({ detail }: { detail: BookingDetail }) {
  const portal = useDashboardPortal();
  const isLive = useDashboardLiveMode();
  const bookingId = detail.summary.id;
  const caps = detail.operationalCapabilities;
  const currency = detail.paymentSummary?.currency ?? detail.summary.currency ?? "PKR";

  const [assignStaffId, setAssignStaffId] = useState(detail.assignment?.staffId ?? "");
  const [assignBusy, setAssignBusy] = useState(false);
  const [assignMsg, setAssignMsg] = useState<string | null>(null);

  const [cancelType, setCancelType] = useState("booking_cancel");
  const [cancelReason, setCancelReason] = useState("");
  const [cancelBusy, setCancelBusy] = useState(false);
  const [cancelMsg, setCancelMsg] = useState<string | null>(null);

  const [refundAmount, setRefundAmount] = useState("");
  const [refundMethod, setRefundMethod] = useState<typeof REFUND_METHODS[number]>("bank_transfer");
  const [refundNotes, setRefundNotes] = useState("");
  const [refundBusy, setRefundBusy] = useState(false);
  const [refundMsg, setRefundMsg] = useState<string | null>(null);

  const [payAmount, setPayAmount] = useState("");
  const [payMethod, setPayMethod] = useState<typeof PAYMENT_METHODS[number]>("bank_transfer");
  const [payReference, setPayReference] = useState("");
  const [payNotes, setPayNotes] = useState("");
  const [payBusy, setPayBusy] = useState(false);
  const [payMsg, setPayMsg] = useState<string | null>(null);

  async function submitAssignment() {
    if (!detail.assignment?.canAssign) return;
    setAssignBusy(true);
    setAssignMsg(null);
    const staffUserId = assignStaffId === "" ? null : Number(assignStaffId);
    const result = await assignBookingStaff(bookingId, staffUserId);
    setAssignBusy(false);
    setAssignMsg(result.ok ? "Assignment updated." : result.message ?? "Assignment failed.");
  }

  async function submitCancellation() {
    if (!caps?.canRequestCancellation) return;
    if (!cancelReason.trim()) {
      setCancelMsg("Reason is required.");
      return;
    }
    setCancelBusy(true);
    setCancelMsg(null);
    const result = await storeCancellationRequest(portal, bookingId, {
      cancellation_type: cancelType,
      reason: cancelReason.trim(),
    });
    setCancelBusy(false);
    setCancelMsg(result.ok ? "Cancellation request recorded." : result.message ?? "Request failed.");
  }

  async function submitRefund() {
    if (!caps?.canCreateRefund) return;
    const amount = Number(refundAmount);
    if (!Number.isFinite(amount) || amount <= 0) {
      setRefundMsg("Enter a valid positive amount.");
      return;
    }
    setRefundBusy(true);
    setRefundMsg(null);
    const result = await storeRefundRequest(portal, bookingId, {
      amount,
      currency,
      method: refundMethod,
      notes: refundNotes.trim() || undefined,
    });
    setRefundBusy(false);
    setRefundMsg(result.ok ? "Refund request recorded." : result.message ?? "Request failed.");
  }

  async function submitPayment() {
    if (!caps?.canRecordPayment) return;
    const amount = Number(payAmount);
    if (!Number.isFinite(amount) || amount <= 0) {
      setPayMsg("Enter a valid positive amount.");
      return;
    }
    setPayBusy(true);
    setPayMsg(null);
    const result = await storeBookingPayment(portal, bookingId, {
      method: payMethod,
      amount,
      payment_reference: payReference.trim() || undefined,
      notes: payNotes.trim() || undefined,
    });
    setPayBusy(false);
    setPayMsg(result.ok ? "Payment recorded." : result.message ?? "Payment failed.");
  }

  return (
    <div className="space-y-4">
      <section id="booking-documents" data-testid="booking-section-booking-documents" className="scroll-mt-24 rounded-2xl border border-jp-border bg-white p-4 shadow-sm">
        <h2 className="text-sm font-semibold text-gray-900">Documents</h2>
        <div className="mt-3">
          {(detail.documents?.length ?? 0) === 0 ? (
            <EmptyState title="No documents" description="No generated documents are on file for this booking yet." />
          ) : (
            <ul className="space-y-2 text-sm">
              {detail.documents?.map((doc) => (
                <li key={doc.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-jp-border px-3 py-2">
                  <span>{doc.title}</span>
                  <a
                    href={doc.downloadPath}
                    className="text-jp-accent underline"
                    data-testid={`booking-document-download-${doc.id}`}
                  >
                    Download
                  </a>
                </li>
              ))}
            </ul>
          )}
        </div>
      </section>

      <section id="booking-cancellation" data-testid="booking-section-booking-cancellation" className="scroll-mt-24 rounded-2xl border border-jp-border bg-white p-4 shadow-sm">
        <h2 className="text-sm font-semibold text-gray-900">Cancellation</h2>
        <p className="mt-1 text-xs text-jp-muted">
          Status: {detail.cancellationState?.bookingCancellationStatus ?? "none"}
        </p>
        {(detail.cancellationState?.requests?.length ?? 0) > 0 ? (
          <ul className="mt-2 space-y-1 text-xs text-jp-muted">
            {detail.cancellationState?.requests.map((row) => (
              <li key={row.id}>
                {row.cancellationType} — {row.status}
                {row.reason ? ` — ${row.reason}` : ""}
              </li>
            ))}
          </ul>
        ) : null}
        {isLive && caps?.canRequestCancellation ? (
          <form
            className="mt-3 space-y-2"
            data-testid="booking-cancellation-form"
            onSubmit={(e) => {
              e.preventDefault();
              void submitCancellation();
            }}
          >
            <label className="block text-xs font-medium text-gray-700">
              Cancellation type
              <select
                className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
                value={cancelType}
                onChange={(e) => setCancelType(e.target.value)}
              >
                {CANCELLATION_TYPES.map((opt) => (
                  <option key={opt.value} value={opt.value}>{opt.label}</option>
                ))}
              </select>
            </label>
            <label className="block text-xs font-medium text-gray-700">
              Reason
              <textarea
                className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
                rows={2}
                value={cancelReason}
                onChange={(e) => setCancelReason(e.target.value)}
                data-testid="booking-cancellation-reason"
              />
            </label>
            {cancelMsg ? <p className="text-sm text-gray-700">{cancelMsg}</p> : null}
            <button type="submit" disabled={cancelBusy} className="min-h-11 rounded-xl bg-jp-accent px-3 py-2 text-sm text-white disabled:opacity-60">
              {cancelBusy ? "Submitting…" : "Submit cancellation request"}
            </button>
          </form>
        ) : (
          <p className="mt-2 text-xs text-jp-muted">Cancellation requests are not available for your role or booking state.</p>
        )}
      </section>

      <section id="booking-refunds" data-testid="booking-section-booking-refunds" className="scroll-mt-24 rounded-2xl border border-jp-border bg-white p-4 shadow-sm">
        <h2 className="text-sm font-semibold text-gray-900">Refunds</h2>
        <p className="mt-1 text-xs text-jp-muted">Status: {detail.refundState?.bookingRefundStatus ?? "none"}</p>
        {isLive && caps?.canCreateRefund ? (
          <form
            className="mt-3 space-y-2"
            data-testid="booking-refund-form"
            onSubmit={(e) => {
              e.preventDefault();
              void submitRefund();
            }}
          >
            <div className="grid gap-2 sm:grid-cols-2">
              <label className="block text-xs font-medium text-gray-700">
                Amount ({currency})
                <input
                  type="number"
                  min={1}
                  className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
                  value={refundAmount}
                  onChange={(e) => setRefundAmount(e.target.value)}
                  data-testid="booking-refund-amount"
                />
              </label>
              <label className="block text-xs font-medium text-gray-700">
                Method
                <select
                  className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
                  value={refundMethod}
                  onChange={(e) => setRefundMethod(e.target.value as typeof refundMethod)}
                >
                  {REFUND_METHODS.map((m) => (
                    <option key={m} value={m}>{m.replaceAll("_", " ")}</option>
                  ))}
                </select>
              </label>
            </div>
            <label className="block text-xs font-medium text-gray-700">
              Notes
              <textarea className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" rows={2} value={refundNotes} onChange={(e) => setRefundNotes(e.target.value)} />
            </label>
            {refundMsg ? <p className="text-sm text-gray-700">{refundMsg}</p> : null}
            <button type="submit" disabled={refundBusy} className="min-h-11 rounded-xl bg-jp-accent px-3 py-2 text-sm text-white disabled:opacity-60">
              {refundBusy ? "Submitting…" : "Submit refund request"}
            </button>
          </form>
        ) : null}
      </section>

      <section id="booking-payments-record" data-testid="booking-section-booking-payments-record" className="scroll-mt-24 rounded-2xl border border-jp-border bg-white p-4 shadow-sm">
        <h2 className="text-sm font-semibold text-gray-900">Record payment</h2>
        {(detail.paymentsHistory?.length ?? 0) > 0 ? (
          <ul className="mt-2 space-y-1 text-xs text-jp-muted">
            {(detail.paymentsHistory ?? []).map((p) => (
              <li key={p.id}>
                {p.amount} {p.currency} — {p.method} — {p.status}
              </li>
            ))}
          </ul>
        ) : null}
        {isLive && caps?.canRecordPayment ? (
          <form
            className="mt-3 space-y-2"
            data-testid="booking-payment-record-form"
            onSubmit={(e) => {
              e.preventDefault();
              void submitPayment();
            }}
          >
            <div className="grid gap-2 sm:grid-cols-2">
              <label className="block text-xs font-medium text-gray-700">
                Amount ({currency})
                <input
                  type="number"
                  min={1}
                  className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
                  value={payAmount}
                  onChange={(e) => setPayAmount(e.target.value)}
                  data-testid="booking-payment-amount"
                />
              </label>
              <label className="block text-xs font-medium text-gray-700">
                Method
                <select className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" value={payMethod} onChange={(e) => setPayMethod(e.target.value as typeof payMethod)}>
                  {PAYMENT_METHODS.map((m) => (
                    <option key={m} value={m}>{m.replaceAll("_", " ")}</option>
                  ))}
                </select>
              </label>
            </div>
            <label className="block text-xs font-medium text-gray-700">
              Reference
              <input className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" value={payReference} onChange={(e) => setPayReference(e.target.value)} />
            </label>
            <label className="block text-xs font-medium text-gray-700">
              Notes
              <textarea className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" rows={2} value={payNotes} onChange={(e) => setPayNotes(e.target.value)} />
            </label>
            {payMsg ? <p className="text-sm text-gray-700">{payMsg}</p> : null}
            <button type="submit" disabled={payBusy} className="min-h-11 rounded-xl bg-jp-accent px-3 py-2 text-sm text-white disabled:opacity-60">
              {payBusy ? "Saving…" : "Record payment"}
            </button>
          </form>
        ) : null}
      </section>

      <section id="booking-communication" data-testid="booking-section-booking-communication" className="scroll-mt-24 rounded-2xl border border-jp-border bg-white p-4 shadow-sm">
        <h2 className="text-sm font-semibold text-gray-900">Communication</h2>
        <p className="mt-1 text-xs text-jp-muted" data-testid="booking-communication-gated">
          Customer email sends are gated in this dashboard release. Review enabled actions and history below; use Laravel back-office to send when required.
        </p>
        <ul className="mt-2 space-y-1 text-xs">
          {Object.entries(caps?.communicationActions ?? {}).map(([action, meta]) => (
            <li key={action} className={meta.enabled ? "text-green-800" : "text-jp-muted"}>
              {action.replaceAll("_", " ")}: {meta.enabled ? "available" : meta.reason ?? "disabled"}
            </li>
          ))}
        </ul>
        {(detail.communicationLogs?.length ?? 0) === 0 ? (
          <p className="mt-2 text-xs text-jp-muted">No communication log entries.</p>
        ) : (
          <ul className="mt-2 space-y-1 text-xs text-jp-muted">
            {detail.communicationLogs?.map((log) => (
              <li key={log.id}>
                {log.channel} / {log.event} — {log.status}
              </li>
            ))}
          </ul>
        )}
      </section>

      <section id="booking-assignment" data-testid="booking-section-booking-assignment" className="scroll-mt-24 rounded-2xl border border-jp-border bg-white p-4 shadow-sm">
        <h2 className="text-sm font-semibold text-gray-900">Assignment</h2>
        <p className="mt-1 text-xs text-jp-muted">
          Current: {detail.assignment?.staffName ?? "Unassigned"}
        </p>
        {isLive && detail.assignment?.canAssign ? (
          <form
            className="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end"
            data-testid="booking-assignment-form"
            onSubmit={(e) => {
              e.preventDefault();
              void submitAssignment();
            }}
          >
            <label className="flex-1 text-xs font-medium text-gray-700">
              Staff member
              <select
                className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm"
                value={assignStaffId}
                onChange={(e) => setAssignStaffId(e.target.value)}
                data-testid="booking-assignment-select"
              >
                <option value="">Unassign</option>
                {detail.assignment?.assignableStaff.map((staff) => (
                  <option key={staff.id} value={String(staff.id)}>{staff.name}</option>
                ))}
              </select>
            </label>
            <button type="submit" disabled={assignBusy} className="min-h-11 shrink-0 rounded-xl border border-jp-border bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50 disabled:opacity-60">
              {assignBusy ? "Saving…" : "Assign"}
            </button>
          </form>
        ) : (
          <p className="mt-2 text-xs text-jp-muted">Staff assignment is limited to authorized platform admins.</p>
        )}
        {assignMsg ? <p className="mt-2 text-sm text-gray-700">{assignMsg}</p> : null}
      </section>

      <section id="booking-activity" data-testid="booking-section-booking-activity" className="scroll-mt-24 rounded-2xl border border-jp-border bg-white p-4 shadow-sm">
        <h2 className="text-sm font-semibold text-gray-900">Audit / activity</h2>
        <ul className="mt-2 space-y-1 text-xs text-jp-muted">
          {(detail.activityTimeline ?? []).map((event, index) => (
            <li key={`${event.type}-${index}`}>
              {event.title} — {event.details}
              {event.occurredAt ? ` (${event.occurredAt})` : ""}
            </li>
          ))}
        </ul>
      </section>

      <section id="booking-operations" data-testid="booking-section-booking-operations" className="scroll-mt-24 rounded-2xl border border-jp-border bg-white p-4 shadow-sm">
        <BookingOperationalActions bookingId={bookingId} />
      </section>
    </div>
  );
}
