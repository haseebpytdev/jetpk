"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { BookingProgress } from "@/features/booking-progress";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
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
  };

  useEffect(() => {
    void loadPayment();
  }, [bookingRef]);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setSubmitting(true);
    setFieldErrors({});
    const formData = new FormData();
    formData.set("payment_method", paymentMethod);
    formData.set("payment_reference", paymentReference);
    if (paymentProof) formData.set("payment_proof", paymentProof);

    const response = await submitGroupPayment(bookingRef, formData);
    setSubmitting(false);
    if (!response.ok) {
      setFieldErrors(mapFieldErrors(response.errors));
      setError(response.message);
      return;
    }
    router.push(response.data.redirect_path);
  };

  if (loading) return <p className="p-8 text-jp-sm text-jp-muted">Loading payment…</p>;
  if (expired) return <div className="p-8"><GroupHoldExpiredState /></div>;
  if (!booking) return <p className="p-8 text-jp-sm text-red-700">{error ?? "Payment unavailable."}</p>;

  return (
    <div className="mx-auto max-w-5xl px-4 py-8">
      <BookingProgress steps={booking.progress} className="mb-6" />
      <h1 className="text-2xl font-semibold text-jp-text">Manual payment</h1>
      <p className="mt-1 text-jp-sm text-jp-muted">Reference: {booking.reference}</p>

      <div className="mt-6 grid gap-6 lg:grid-cols-[2fr_1fr]">
        <form onSubmit={(event) => void handleSubmit(event)} className="space-y-4" data-testid="group-payment-form">
          <GroupHoldCountdown
            expiresAt={booking.expires_at}
            serverTime={booking.server_time}
            onExpired={() => setExpired(true)}
          />

          <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
            <h2 className="text-jp-base font-semibold">Payment method</h2>
            <p className="text-jp-sm text-jp-muted">Manual payment only. Card and wallet options are not available for group bookings.</p>
            <div className="mt-3 space-y-2">
              {booking.payment_methods.map((method) => (
                <label key={method.value} className="flex cursor-pointer gap-3 rounded-jp-md border border-jp-border p-3 has-[:checked]:border-jp-primary">
                  <input
                    type="radio"
                    name="payment_method"
                    value={method.value}
                    checked={paymentMethod === method.value}
                    onChange={() => setPaymentMethod(method.value)}
                  />
                  <span>
                    <span className="block text-jp-sm font-semibold">{method.title}</span>
                    <span className="block text-jp-xs text-jp-muted">{method.hint}</span>
                  </span>
                </label>
              ))}
            </div>
          </div>

          <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
            <h2 className="text-jp-base font-semibold">Payment details</h2>
            <p className="text-jp-sm text-jp-muted">Amount due: {booking.currency} {booking.total_formatted}</p>
            <ul className="mt-2 list-disc pl-5 text-jp-sm text-jp-muted">
              {booking.instructions.map((line) => <li key={line}>{line}</li>)}
            </ul>
            <label className="mt-4 block text-jp-sm">Payment reference / transaction ID
              <input
                required
                value={paymentReference}
                onChange={(e) => setPaymentReference(e.target.value)}
                className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2"
              />
            </label>
            {fieldErrors.payment_reference ? <p className="text-jp-sm text-red-700">{fieldErrors.payment_reference}</p> : null}
            {booking.payment_proof_supported ? (
              <label className="mt-3 block text-jp-sm">Upload payment proof (optional)
                <input type="file" accept=".jpg,.jpeg,.png,.pdf" onChange={(e) => setPaymentProof(e.target.files?.[0] ?? null)} className="mt-1 w-full" />
              </label>
            ) : null}
          </div>

          {error ? <p className="text-jp-sm text-red-700" role="alert">{error}</p> : null}
          <PrimaryButton type="submit" disabled={submitting || expired}>{submitting ? "Submitting…" : "Submit payment for review"}</PrimaryButton>
        </form>

        <aside className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
          <p className="text-jp-xs uppercase tracking-wide text-jp-muted">Booking status</p>
          <p className="mt-1 text-jp-sm font-semibold">{booking.status_label}</p>
          <p className="mt-3 text-jp-xs uppercase tracking-wide text-jp-muted">Payment status</p>
          <p className="mt-1 text-jp-sm">{booking.payment_status_label}</p>
        </aside>
      </div>
    </div>
  );
}
