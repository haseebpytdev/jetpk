"use client";

import { useEffect, useId, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { BookingProgress } from "@/features/booking-progress";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { FieldError, FieldHelp, FieldLabel, FormErrorSummary, TextInput } from "@/components/ui/FormControls";
import { cn } from "@/lib/cn";
import { mapFieldErrors } from "@/features/auth/utils/laravel-auth-api";
import { fetchGroupPayment, submitGroupPayment } from "../services/group-ticketing-api";
import type { GroupPaymentInstructions } from "../types";
import { GroupHoldCountdown } from "./GroupHoldCountdown";
import { GroupHoldExpiredState } from "./GroupStateCards";

type GroupPaymentPageProps = {
  bookingRef: string;
};

export function GroupPaymentPage({ bookingRef }: GroupPaymentPageProps) {
  const router = useRouter();
  const referenceId = useId();
  const proofId = useId();
  const referenceInputRef = useRef<HTMLInputElement>(null);
  const [booking, setBooking] = useState<GroupPaymentInstructions | null>(null);
  const [paymentMethod, setPaymentMethod] = useState("bank_transfer");
  const [paymentReference, setPaymentReference] = useState("");
  const [paymentProof, setPaymentProof] = useState<File | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [loading, setLoading] = useState(true);
  const [expired, setExpired] = useState(false);

  const loadPayment = async () => {
    const response = await fetchGroupPayment(bookingRef);
    setLoading(false);
    if (!response.ok) {
      if (response.status === 401) {
        router.push(`/login?redirect=${encodeURIComponent(`/groups/booking/${bookingRef}/payment`)}`);
        return;
      }
      if (response.status === 410) setExpired(true);
      setError(response.message);
      return;
    }
    const data = response.data;
    if ("redirect_path" in data && data.redirect_path) {
      router.replace(data.redirect_path);
      return;
    }
    setBooking(data);
    if (data.payment_methods[0]?.value) {
      setPaymentMethod(data.payment_methods[0].value);
    }
  };

  useEffect(() => {
    void loadPayment();
  }, [bookingRef]);

  const validateClient = (): Record<string, string> => {
    const next: Record<string, string> = {};
    if ((booking?.payment_reference_required ?? true) && paymentReference.trim() === "") {
      next.payment_reference = "Enter your payment reference or transaction ID.";
    }
    return next;
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (submitting || expired) return;

    setError(null);
    const clientErrors = validateClient();
    if (Object.keys(clientErrors).length > 0) {
      setFieldErrors(clientErrors);
      referenceInputRef.current?.focus();
      return;
    }

    setSubmitting(true);
    setFieldErrors({});
    const formData = new FormData();
    formData.set("payment_method", paymentMethod);
    formData.set("payment_reference", paymentReference.trim());
    if (paymentProof) formData.set("payment_proof", paymentProof);

    const response = await submitGroupPayment(bookingRef, formData);
    setSubmitting(false);
    if (!response.ok) {
      const mapped = mapFieldErrors(response.errors);
      setFieldErrors(mapped);
      setError(response.message);
      if (mapped.payment_reference) {
        referenceInputRef.current?.focus();
      }
      return;
    }
    router.push(response.data.redirect_path);
  };

  if (loading) return <p className="p-8 text-jp-sm text-jp-muted">Loading payment…</p>;
  if (expired) return <div className="p-8"><GroupHoldExpiredState /></div>;
  if (!booking) return <p className="p-8 text-jp-sm text-red-700">{error ?? "Payment unavailable."}</p>;

  const summaryErrors = Object.values(fieldErrors);
  const routeLine = booking.inventory?.route_line;
  const packageTitle = booking.inventory?.title ?? booking.inventory?.airline_name;

  return (
    <div className="jp-fab-content-clear mx-auto max-w-5xl px-4 py-6 sm:py-8">
      <header className="space-y-1">
        <h1 className="text-2xl font-semibold text-jp-text">Complete payment</h1>
        <p className="text-jp-sm text-jp-muted">
          Booking reference <span className="font-medium text-jp-text">{booking.reference}</span>
        </p>
      </header>

      <BookingProgress steps={booking.progress} className="mt-5 mb-6" />

      <div className="mt-6 grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(16rem,1fr)] lg:items-start">
        <form
          onSubmit={(event) => void handleSubmit(event)}
          noValidate
          className="order-2 min-w-0 space-y-5 lg:order-1"
          data-testid="group-payment-form"
        >
          <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 sm:p-5" aria-labelledby="group-payment-status-heading">
            <h2 id="group-payment-status-heading" className="text-jp-base font-semibold text-jp-text">
              Current status
            </h2>
            <p className="mt-1 text-jp-sm text-jp-muted">
              {booking.status_message ?? "Awaiting manual payment submission"}
            </p>
            <div className="mt-4">
              <GroupHoldCountdown
                expiresAt={booking.expires_at}
                serverTime={booking.server_time}
                onExpired={() => setExpired(true)}
              />
            </div>
          </section>

          <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 sm:p-5" aria-labelledby="group-payment-method-heading">
            <h2 id="group-payment-method-heading" className="text-jp-base font-semibold text-jp-text">
              Payment method
            </h2>
            <p className="mt-1 text-jp-sm text-jp-muted">
              Manual payment only. Card and wallet options are not available for group bookings.
            </p>
            <div className="mt-4 space-y-3" role="radiogroup" aria-labelledby="group-payment-method-heading">
              {booking.payment_methods.map((method) => {
                const selected = paymentMethod === method.value;
                return (
                  <label
                    key={method.value}
                    className={cn(
                      "flex cursor-pointer gap-3 rounded-jp-lg border-2 bg-jp-surface p-3.5 transition-colors sm:p-4",
                      selected
                        ? "border-jp-primary bg-jp-primary-soft/50 shadow-jp-sm"
                        : "border-jp-border hover:border-jp-primary/50",
                    )}
                    data-testid={`group-payment-method-${method.value}`}
                  >
                    <input
                      type="radio"
                      name="payment_method"
                      value={method.value}
                      checked={selected}
                      onChange={() => setPaymentMethod(method.value)}
                      className="mt-1 h-4 w-4 shrink-0 border-jp-border text-jp-brand focus-visible:shadow-jp-focus"
                    />
                    <span className="min-w-0 space-y-1">
                      <span className="block text-jp-sm font-semibold leading-snug text-jp-text">{method.title}</span>
                      <span className="block text-jp-xs leading-relaxed text-jp-muted">{method.hint}</span>
                    </span>
                  </label>
                );
              })}
            </div>
          </section>

          <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 sm:p-5" aria-labelledby="group-payment-details-heading">
            <h2 id="group-payment-details-heading" className="text-jp-base font-semibold text-jp-text">
              Payment details
            </h2>
            <div className="mt-3 rounded-jp-md border border-jp-border bg-jp-page/50 px-3 py-3">
              <p className="text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">Amount due</p>
              <p className="mt-1 text-lg font-semibold tabular-nums text-jp-text">
                {booking.currency} {booking.total_formatted}
              </p>
            </div>

            {booking.instructions.length > 0 ? (
              <ul className="mt-4 list-disc space-y-1.5 pl-5 text-jp-sm text-jp-muted">
                {booking.instructions.map((line) => (
                  <li key={line}>{line}</li>
                ))}
              </ul>
            ) : null}

            <div className="mt-5 space-y-2">
              <FieldLabel htmlFor={referenceId} required={booking.payment_reference_required}>
                Payment reference / transaction ID
              </FieldLabel>
              <FieldHelp>Use the bank or office reference shown on your receipt.</FieldHelp>
              <TextInput
                ref={referenceInputRef}
                id={referenceId}
                name="payment_reference"
                value={paymentReference}
                onChange={(e) => {
                  setPaymentReference(e.target.value);
                  if (fieldErrors.payment_reference) {
                    setFieldErrors((prev) => {
                      const next = { ...prev };
                      delete next.payment_reference;
                      return next;
                    });
                  }
                }}
                autoComplete="off"
                aria-invalid={fieldErrors.payment_reference ? true : undefined}
                aria-describedby={fieldErrors.payment_reference ? `${referenceId}-error` : undefined}
                className={cn(fieldErrors.payment_reference && "border-jp-danger")}
                data-testid="group-payment-reference"
              />
              {fieldErrors.payment_reference ? (
                <FieldError id={`${referenceId}-error`}>{fieldErrors.payment_reference}</FieldError>
              ) : null}
            </div>

            {booking.payment_proof_supported ? (
              <div className="mt-5 space-y-2">
                <FieldLabel htmlFor={proofId}>Proof of payment</FieldLabel>
                <FieldHelp>Optional. JPG, PNG, or PDF.</FieldHelp>
                <input
                  id={proofId}
                  type="file"
                  accept=".jpg,.jpeg,.png,.pdf"
                  onChange={(e) => setPaymentProof(e.target.files?.[0] ?? null)}
                  className="block w-full min-w-0 text-jp-sm text-jp-text file:mr-3 file:rounded-jp-md file:border-0 file:bg-jp-primary-soft file:px-3 file:py-2 file:text-jp-sm file:font-medium file:text-jp-primary"
                  data-testid="group-payment-proof"
                />
                {paymentProof ? (
                  <p className="text-jp-xs text-jp-muted">Selected: {paymentProof.name}</p>
                ) : null}
                {fieldErrors.payment_proof ? (
                  <FieldError>{fieldErrors.payment_proof}</FieldError>
                ) : null}
              </div>
            ) : null}
          </section>

          {summaryErrors.length > 0 ? <FormErrorSummary errors={summaryErrors} /> : null}
          {error ? (
            <p className="text-jp-sm text-jp-danger" role="alert">
              {error}
            </p>
          ) : null}

          <div
            className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 sm:p-5 max-lg:jp-fab-content-clear"
            data-testid="group-payment-final-action"
          >
            <PrimaryButton
              type="submit"
              fullWidth
              className="w-full max-lg:!w-full lg:!w-auto lg:min-w-[14rem]"
              disabled={submitting || expired}
              data-testid="group-payment-submit"
            >
              {submitting ? "Submitting…" : "Submit payment for review"}
            </PrimaryButton>
            <p className="mt-2 text-jp-xs text-jp-muted">
              Your booking stays on hold until our team reviews this payment.
            </p>
          </div>
        </form>

        <aside className="order-1 min-w-0 space-y-4 max-lg:mb-2 lg:sticky lg:top-24 lg:order-2">
          <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" aria-labelledby="group-booking-summary-heading">
            <h2 id="group-booking-summary-heading" className="text-jp-base font-semibold text-jp-text">
              Booking summary
            </h2>
            <dl className="mt-3 space-y-3 text-jp-sm">
              <div>
                <dt className="text-jp-xs uppercase tracking-wide text-jp-muted">Reference</dt>
                <dd className="mt-0.5 font-medium text-jp-text">{booking.reference}</dd>
              </div>
              {packageTitle || routeLine ? (
                <div>
                  <dt className="text-jp-xs uppercase tracking-wide text-jp-muted">Package / route</dt>
                  <dd className="mt-0.5 text-jp-text">
                    {packageTitle ? <span className="block font-medium">{packageTitle}</span> : null}
                    {routeLine ? <span className="block text-jp-muted">{routeLine}</span> : null}
                  </dd>
                </div>
              ) : null}
              <div>
                <dt className="text-jp-xs uppercase tracking-wide text-jp-muted">Passengers</dt>
                <dd className="mt-0.5 font-medium text-jp-text">
                  {booking.seat_count} seat{booking.seat_count === 1 ? "" : "s"}
                </dd>
              </div>
              <div>
                <dt className="text-jp-xs uppercase tracking-wide text-jp-muted">Total amount</dt>
                <dd className="mt-0.5 font-semibold tabular-nums text-jp-text">
                  {booking.currency} {booking.total_formatted}
                </dd>
              </div>
              <div>
                <dt className="text-jp-xs uppercase tracking-wide text-jp-muted">Booking status</dt>
                <dd className="mt-0.5 text-jp-text">{booking.status_label}</dd>
              </div>
              <div>
                <dt className="text-jp-xs uppercase tracking-wide text-jp-muted">Payment status</dt>
                <dd className="mt-0.5 text-jp-text">{booking.payment_status_label}</dd>
              </div>
            </dl>
          </section>
        </aside>
      </div>
    </div>
  );
}
