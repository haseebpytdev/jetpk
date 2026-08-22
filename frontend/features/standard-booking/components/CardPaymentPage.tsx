"use client";

import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { BookingProgress } from "@/features/booking-progress";
import {
  BookingLayout,
  BookingLoadingState,
  BookingMainColumn,
  BookingPageHeader,
  BookingPageShell,
  BookingSection,
  BookingSectionHeader,
  BookingSidebar,
  OrderSummary,
} from "@/features/booking-layout";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { fetchCheckoutState, startCardPayment } from "../services/booking-checkout-api";
import { useBookingStatusPoll } from "../hooks/useBookingStatusPoll";
import type { CheckoutState } from "../types/review-payment";
import { MissingBookingSessionState } from "./BookingStateCards";
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

  if (loading) return <BookingLoadingState message="Loading card payment…" />;
  if (!state?.ok) return <div className="mx-auto max-w-jp-booking p-8"><MissingBookingSessionState /></div>;

  const card = state.card_payment;

  return (
    <BookingPageShell testId="card-payment-page">
      <BookingProgress steps={state.booking_session.progress} className="mb-6" />
      <BookingPageHeader
        title="Secure card payment"
        description={`Booking reference: ${state.booking_reference ?? "—"}`}
      />

      <BookingLayout
        main={
          <BookingMainColumn>
            <BookingSection>
              <BookingSectionHeader title="Amount due" />
              <p className="text-2xl font-semibold tabular-nums text-jp-text" data-testid="card-payment-amount">
                {card?.formatted_amount ?? state.pricing.formatted_total}
              </p>
              <p className="mt-2 text-jp-sm text-jp-muted">{card?.ticketing_note}</p>
              <p className="mt-2 text-jp-sm">
                Payment status: <strong data-testid="card-payment-status">{card?.payment_status_label ?? state.payment_status.label}</strong>
              </p>
              {card?.blocked_message ? (
                <p className="mt-2 text-jp-sm text-amber-800 dark:text-amber-200" role="alert">{card.blocked_message}</p>
              ) : null}
              {card?.show_pay_button ? (
                <PrimaryButton
                  type="button"
                  className="mt-4"
                  disabled={starting}
                  onClick={() => void handlePay()}
                  data-testid="card-pay-button"
                >
                  {starting ? "Starting payment…" : "Pay securely"}
                </PrimaryButton>
              ) : (
                <p className="mt-4 text-jp-sm text-jp-muted">Online payment is not available for this booking right now.</p>
              )}
              <p className="mt-3 text-jp-sm text-jp-muted">
                You&apos;ll continue to our secure card payment provider. JetPakistan does not collect or store your card details.
              </p>
              <p className="mt-2 text-jp-xs text-jp-muted">Powered by AbhiPay</p>
            </BookingSection>
          </BookingMainColumn>
        }
        sidebar={
          <BookingSidebar>
            <OrderSummary
              itinerary={state.itinerary}
              travellerTotal={state.passengers.length}
              pricing={state.pricing}
              paymentStatus={state.payment_status}
              variant="flight-preview"
              testId="card-payment-order-summary"
            />
          </BookingSidebar>
        }
      />
      {error ? <p className="mt-4 text-jp-sm text-red-700 dark:text-red-300" role="alert">{error}</p> : null}
    </BookingPageShell>
  );
}

export function PaymentStatusPage({ reference }: { reference?: string }) {
  const { data, loading, error, polling, timedOut, reload } = useBookingStatusPoll({ mode: "payment", reference });
  const payload = data as import("../types/review-payment").PaymentStatusResponse | null;

  if (loading && !payload) {
    return (
      <div className="p-8" aria-busy="true" aria-label="Verifying payment" role="status">
        <p className="text-jp-sm text-jp-muted">Verifying payment…</p>
      </div>
    );
  }

  if (!payload?.ok) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-8">
        <MissingBookingSessionState />
        {error ? <p className="mt-4 text-jp-sm text-red-700" role="alert">{error}</p> : null}
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-3xl px-4 py-8" data-testid="payment-status-page" aria-live="polite" aria-busy={polling}>
      <h1 className="text-2xl font-semibold text-jp-text">Payment status</h1>
      <p className="mt-1 text-jp-sm text-jp-muted">Reference: {payload.booking_reference ?? "—"}</p>
      {polling ? <p className="mt-2 text-jp-sm text-jp-muted" role="status">Checking for updates…</p> : null}
      {timedOut ? (
        <div className="mt-4 rounded-jp-md border border-amber-300 bg-amber-50 p-3 text-jp-sm text-amber-950" role="alert">
          <p>{error}</p>
          <button type="button" className="mt-2 font-semibold underline" onClick={() => void reload()}>
            Refresh status
          </button>
        </div>
      ) : null}

      <div className="mt-6 grid gap-4 sm:grid-cols-3">
        <article className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
          <h2 className="text-jp-sm font-semibold">Payment</h2>
          <p className="mt-2 text-jp-sm" data-testid="payment-status-label">{payload.payment_status.label}</p>
        </article>
        <article className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
          <h2 className="text-jp-sm font-semibold">Booking</h2>
          <p className="mt-2 text-jp-sm">{payload.booking_status.label}</p>
        </article>
        {payload.ticketing_status ? (
          <article className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
            <h2 className="text-jp-sm font-semibold">Ticketing</h2>
            <p className="mt-2 text-jp-sm">{payload.ticketing_status.label}</p>
          </article>
        ) : null}
      </div>

      {payload.transaction_reference ? (
        <p className="mt-4 text-jp-sm text-jp-muted">Transaction reference: {payload.transaction_reference}</p>
      ) : null}

      <div className="mt-6 flex flex-wrap gap-3 print:hidden">
        {payload.payment_status.code === "succeeded" && payload.confirmation_url ? (
          <a href={payload.confirmation_url} className="rounded-jp-button bg-jp-primary px-4 py-2 text-jp-sm font-semibold text-white">
            Continue to confirmation
          </a>
        ) : null}
        {payload.invoice_url ? (
          <a href={payload.invoice_url} className="rounded-jp-button border border-jp-border px-4 py-2 text-jp-sm font-semibold">
            View invoice
          </a>
        ) : null}
      </div>
    </div>
  );
}
