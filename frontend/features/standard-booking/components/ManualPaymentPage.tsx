"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
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
import { fetchCheckoutState } from "../services/booking-checkout-api";
import type { CheckoutState } from "../types/review-payment";
import { MissingBookingSessionState } from "./BookingStateCards";
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

  if (loading && !state) {
    return (
      <BookingPageShell testId="payment-loading-shell">
        <BookingPageHeader title="Payment" description="Loading payment instructions…" />
        <BookingLoadingState message="Loading payment status…" />
      </BookingPageShell>
    );
  }
  if (!state?.ok) return <div className="mx-auto max-w-jp-booking p-8"><MissingBookingSessionState /></div>;

  const manual = state.manual_payment;
  const handoff = state.confirmation_handoff_url;

  return (
    <BookingPageShell testId="manual-payment-page">
      <BookingProgress steps={state.booking_session.progress} className="mb-6" />
      <BookingPageHeader
        title="Manual payment"
        description={`Reference: ${state.booking_reference ?? "—"}`}
      />

      <BookingLayout
        main={
          <BookingMainColumn>
            <BookingSection>
              <BookingSectionHeader title="Amount due" />
              <p className="text-2xl font-semibold text-jp-text" data-testid="manual-amount-due">
                {manual?.formatted_amount ?? state.pricing.formatted_total}
              </p>
              <ul className="mt-4 list-disc space-y-2 pl-5 text-jp-sm text-jp-muted">
                {(manual?.instructions ?? []).map((line) => <li key={line}>{line}</li>)}
              </ul>
              <p className="mt-4 text-jp-sm">
                Payment status: <strong>{manual?.payment_status_label ?? state.payment_status.label}</strong>
              </p>
              <p className="mt-1 text-jp-sm">
                Booking status: <strong>{state.booking_status.label}</strong>
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
            <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 text-jp-sm">
              <p className="font-semibold">Need help?</p>
              <Link href="/support" className="mt-2 block text-jp-primary">Contact support</Link>
              <Link href="/lookup-booking" className="mt-1 block text-jp-primary">Lookup booking</Link>
              <Link href="/booking/invoice" className="mt-1 block text-jp-primary">View invoice</Link>
            </div>
            {isAllowedConfirmationHandoff(handoff) ? (
              <PrimaryButton type="button" onClick={() => router.push(handoff!)}>View confirmation</PrimaryButton>
            ) : null}
          </BookingSidebar>
        }
      />
      {error ? <p className="mt-4 text-jp-sm text-red-700 dark:text-red-300" role="alert">{error}</p> : null}
    </BookingPageShell>
  );
}
