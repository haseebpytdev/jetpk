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
      <BookingProgress steps={state.booking_session.progress} className="mb-6" compact />
      <BookingPageHeader
        title="Pay with AbhiPay"
        description={`Reference: ${state.booking_reference ?? "—"}`}
      />

      <BookingLayout
        main={
          <BookingMainColumn>
            <BookingSection>
              <BookingSectionHeader title="Card payment" />
              <p className="text-2xl font-semibold text-jp-text">{card?.formatted_amount ?? state.pricing.formatted_total}</p>
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
                  {starting ? "Starting payment…" : "Pay securely with AbhiPay"}
                </PrimaryButton>
              ) : (
                <p className="mt-4 text-jp-sm text-jp-muted">Online payment is not available for this booking right now.</p>
              )}
              <p className="mt-3 text-jp-xs text-jp-muted">
                You will be redirected to the secure AbhiPay checkout. Card details are not collected on this page.
              </p>
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
            />
          </BookingSidebar>
        }
      />
      {error ? <p className="mt-4 text-jp-sm text-red-700 dark:text-red-300" role="alert">{error}</p> : null}
    </BookingPageShell>
  );
}

export function PaymentStatusPage({ reference }: { reference?: string }) {
  const { data, loading, error } = useBookingStatusPoll({ mode: "payment", reference });
  const payload = data as import("../types/review-payment").PaymentStatusResponse | null;

  if (loading) {
    return <p className="p-8 text-jp-sm text-jp-muted">Verifying payment…</p>;
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
    <div className="mx-auto max-w-3xl px-4 py-8" data-testid="payment-status-page" aria-live="polite">
      <h1 className="text-2xl font-semibold text-jp-text">Payment status</h1>
      <p className="mt-1 text-jp-sm text-jp-muted">Reference: {payload.booking_reference ?? "—"}</p>

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
