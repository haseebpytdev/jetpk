"use client";

import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { BookingProgress } from "@/features/booking-progress";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { fetchCheckoutState, fetchPaymentStatus, startCardPayment } from "../services/booking-checkout-api";
import type { CheckoutState } from "../types/review-payment";
import { MissingBookingSessionState } from "./BookingStateCards";
import { ReviewPriceBreakdown } from "./ReviewPriceBreakdown";
import { isAllowedHostedCheckoutUrl, resolveLaravelPostUrl } from "../utils/payment-url";

export function CardPaymentPage() {
  const router = useRouter();
  const [state, setState] = useState<CheckoutState | null>(null);
  const [loading, setLoading] = useState(true);
  const [starting, setStarting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const startLock = useRef(false);

  const load = async () => {
    const response = await fetchCheckoutState();
    setLoading(false);
    if (!response.ok) {
      setError(response.message);
      return;
    }
    setState(response.data);
    if (response.data.payment_status.code === "succeeded") {
      router.replace("/booking/payment/status");
    }
  };

  useEffect(() => {
    void load();
  }, []);

  const handlePay = async () => {
    const card = state?.card_payment;
    if (!card?.start_endpoint || !card.show_pay_button || startLock.current) return;
    const postUrl = resolveLaravelPostUrl(card.start_endpoint);
    if (!postUrl) {
      setError("Payment could not be started.");
      return;
    }

    startLock.current = true;
    setStarting(true);
    setError(null);

    const response = await startCardPayment(card.start_endpoint);
    setStarting(false);

    if (!response.ok || !response.data.ok || !response.data.redirect_url) {
      startLock.current = false;
      const failMessage = !response.ok
        ? response.message
        : response.data.message ?? "Payment could not be started.";
      setError(failMessage);
      return;
    }

    if (!isAllowedHostedCheckoutUrl(response.data.redirect_url)) {
      startLock.current = false;
      setError("Payment redirect was rejected.");
      return;
    }

    window.location.assign(response.data.redirect_url);
  };

  if (loading) return <p className="p-8 text-jp-sm text-jp-muted">Loading card payment…</p>;
  if (!state?.ok) return <div className="mx-auto max-w-3xl p-8"><MissingBookingSessionState /></div>;

  const card = state.card_payment;

  return (
    <div className="mx-auto max-w-4xl px-4 py-8" data-testid="card-payment-page">
      <BookingProgress steps={state.booking_session.progress} className="mb-6" />
      <h1 className="text-2xl font-semibold text-jp-text">Pay by card</h1>
      <p className="mt-1 text-jp-sm text-jp-muted">Reference: {state.booking_reference ?? "—"}</p>

      <div className="mt-6 grid gap-6 lg:grid-cols-[2fr_1fr]">
        <article className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
          <h2 className="text-jp-base font-semibold">Card payment</h2>
          <p className="mt-2 text-xl font-semibold">{card?.formatted_amount ?? state.pricing.formatted_total}</p>
          <p className="mt-2 text-jp-sm text-jp-muted">{card?.ticketing_note}</p>
          <p className="mt-2 text-jp-sm">Payment status: <strong data-testid="card-payment-status">{card?.payment_status_label ?? state.payment_status.label}</strong></p>
          {card?.blocked_message ? <p className="mt-2 text-jp-sm text-amber-800" role="alert">{card.blocked_message}</p> : null}
          {card?.show_pay_button ? (
            <PrimaryButton
              type="button"
              className="mt-4"
              disabled={starting}
              onClick={() => void handlePay()}
              data-testid="card-pay-button"
            >
              {starting ? "Starting payment…" : "Pay with card"}
            </PrimaryButton>
          ) : (
            <p className="mt-4 text-jp-sm text-jp-muted">Online payment is not available for this booking right now.</p>
          )}
        </article>
        <ReviewPriceBreakdown pricing={state.pricing} />
      </div>
      {error ? <p className="mt-4 text-jp-sm text-red-700" role="alert">{error}</p> : null}
    </div>
  );
}

export function PaymentStatusPage({ reference }: { reference?: string }) {
  const [message, setMessage] = useState("Verifying payment…");
  const [statusCode, setStatusCode] = useState("processing");

  useEffect(() => {
    let attempts = 0;
    let cancelled = false;
    let timer: ReturnType<typeof setTimeout> | undefined;

    const poll = async () => {
      const response = await fetchPaymentStatus(reference);
      if (cancelled) return;

      if (!response.ok) {
        setMessage(response.message);
        setStatusCode("failed");
        return;
      }

      const payment = response.data.payment_status;
      setStatusCode(payment.code);
      setMessage(payment.label);

      if (response.data.poll?.should_poll && attempts < (response.data.poll.max_attempts ?? 40)) {
        attempts += 1;
        timer = setTimeout(() => void poll(), response.data.poll.interval_ms ?? 3000);
      }
    };

    void poll();

    return () => {
      cancelled = true;
      if (timer) clearTimeout(timer);
    };
  }, [reference]);

  return (
    <div className="mx-auto max-w-3xl px-4 py-8" data-testid="payment-status-page" aria-live="polite">
      <h1 className="text-2xl font-semibold text-jp-text">Payment status</h1>
      <p className="mt-4 text-jp-sm" data-testid="payment-status-label">{message}</p>
      <p className="mt-2 text-jp-xs text-jp-muted">Status code: {statusCode}</p>
      {statusCode === "succeeded" ? (
        <a href="/booking/confirmation" className="mt-4 inline-block text-jp-primary">Continue to confirmation</a>
      ) : null}
    </div>
  );
}
