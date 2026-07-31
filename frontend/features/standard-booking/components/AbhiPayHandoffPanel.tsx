"use client";

import { useRef, useState } from "react";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { startCardPayment } from "../services/booking-checkout-api";
import type { CheckoutState } from "../types/review-payment";
import { isAllowedHostedCheckoutUrl, resolveLaravelPostUrl } from "../utils/payment-url";

type AbhiPayHandoffPanelProps = {
  state: CheckoutState;
};

/** AbhiPay secure handoff inside the blueprint card-details region — no direct card fields. */
export function AbhiPayHandoffPanel({ state }: AbhiPayHandoffPanelProps) {
  const card = state.card_payment;
  const [starting, setStarting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const startLock = useRef(false);

  const handlePay = async () => {
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
      setError(!response.ok ? response.message : response.data.message ?? "Payment could not be started.");
      return;
    }

    if (!isAllowedHostedCheckoutUrl(response.data.redirect_url)) {
      startLock.current = false;
      setError("Payment redirect was rejected.");
      return;
    }

    window.location.assign(response.data.redirect_url);
  };

  return (
    <div className="rounded-jp-lg border border-jp-border bg-jp-surface-muted p-jp-lg" data-testid="abhipay-handoff-panel">
      <h2 className="font-display text-jp-heading-sm font-bold text-jp-text">Secure card payment</h2>
      <p className="mt-2 text-jp-sm text-jp-muted">
        You will complete payment on the secure AbhiPay checkout. Card details are not collected on this page.
      </p>
      <p className="mt-4 text-2xl font-semibold text-jp-text">{card?.formatted_amount ?? state.pricing.formatted_total}</p>
      {card?.ticketing_note ? <p className="mt-2 text-jp-sm text-jp-muted">{card.ticketing_note}</p> : null}
      {card?.blocked_message ? (
        <p className="mt-2 text-jp-sm text-jp-warning" role="alert">{card.blocked_message}</p>
      ) : null}
      {card?.show_pay_button ? (
        <PrimaryButton
          type="button"
          className="mt-6 w-full sm:w-auto"
          disabled={starting}
          onClick={() => void handlePay()}
          data-testid="card-pay-button"
        >
          {starting ? "Starting secure payment…" : "Continue to Secure Payment →"}
        </PrimaryButton>
      ) : (
        <p className="mt-4 text-jp-sm text-jp-muted">Online payment is not available for this booking right now.</p>
      )}
      <p className="mt-4 flex items-center gap-2 text-jp-xs text-jp-muted">
        <span className="inline-flex h-6 w-6 items-center justify-center rounded-full bg-jp-primary-soft text-jp-primary" aria-hidden="true">🔒</span>
        100% secure payment via AbhiPay
      </p>
      {error ? <p className="mt-4 text-jp-sm text-jp-danger" role="alert">{error}</p> : null}
    </div>
  );
}
