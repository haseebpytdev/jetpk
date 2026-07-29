"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { BookingProgress } from "@/features/booking-progress";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { fetchCheckoutState } from "../services/booking-checkout-api";
import type { CheckoutState } from "../types/review-payment";
import { MissingBookingSessionState } from "./BookingStateCards";
import { ReviewPriceBreakdown } from "./ReviewPriceBreakdown";
import { isAllowedConfirmationHandoff } from "../utils/payment-url";

export function ManualPaymentPage() {
  const router = useRouter();
  const [state, setState] = useState<CheckoutState | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    void fetchCheckoutState().then((response) => {
      setLoading(false);
      if (!response.ok) {
        setError(response.message);
        return;
      }
      setState(response.data);
    });
  }, []);

  if (loading) return <p className="p-8 text-jp-sm text-jp-muted">Loading payment status…</p>;
  if (!state?.ok) return <div className="mx-auto max-w-3xl p-8"><MissingBookingSessionState /></div>;

  const manual = state.manual_payment;
  const handoff = state.confirmation_handoff_url;

  return (
    <div className="mx-auto max-w-4xl px-4 py-8" data-testid="manual-payment-page">
      <BookingProgress steps={state.booking_session.progress} className="mb-6" />
      <h1 className="text-2xl font-semibold text-jp-text">Manual payment</h1>
      <p className="mt-1 text-jp-sm text-jp-muted">Reference: {state.booking_reference ?? "—"}</p>

      <div className="mt-6 grid gap-6 lg:grid-cols-[2fr_1fr]">
        <article className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
          <h2 className="text-jp-base font-semibold">Amount due</h2>
          <p className="mt-2 text-xl font-semibold" data-testid="manual-amount-due">{manual?.formatted_amount ?? state.pricing.formatted_total}</p>
          <ul className="mt-4 list-disc space-y-2 pl-5 text-jp-sm text-jp-muted">
            {(manual?.instructions ?? []).map((line) => <li key={line}>{line}</li>)}
          </ul>
          <p className="mt-4 text-jp-sm">Payment status: <strong>{manual?.payment_status_label ?? state.payment_status.label}</strong></p>
          <p className="mt-1 text-jp-sm">Booking status: <strong>{state.booking_status.label}</strong></p>
        </article>
        <aside className="space-y-4">
          <ReviewPriceBreakdown pricing={state.pricing} />
          <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 text-jp-sm">
            <p className="font-semibold">Need help?</p>
            <Link href="/support" className="mt-2 block text-jp-primary">Contact support</Link>
            <Link href="/lookup-booking" className="mt-1 block text-jp-primary">Lookup booking</Link>
            <Link href="/booking/invoice" className="mt-1 block text-jp-primary">View invoice</Link>
          </div>
          {isAllowedConfirmationHandoff(handoff) ? (
            <PrimaryButton type="button" onClick={() => router.push(handoff!)}>View confirmation</PrimaryButton>
          ) : null}
        </aside>
      </div>
      {error ? <p className="mt-4 text-jp-sm text-red-700" role="alert">{error}</p> : null}
    </div>
  );
}
