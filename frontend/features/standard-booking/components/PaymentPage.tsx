"use client";

import { Suspense, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
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
import { PaymentMethodSelector } from "@/features/standard-booking/components/PaymentMethodSelector";
import { fetchCheckoutState } from "@/features/standard-booking/services/booking-checkout-api";
import type { CheckoutState, PaymentMethodCode } from "@/features/standard-booking/types/review-payment";
import { MissingBookingSessionState } from "@/features/standard-booking/components/BookingStateCards";
import { AbhiPayHandoffPanel } from "./AbhiPayHandoffPanel";
import { ManualPaymentForm } from "./ManualPaymentForm";

function PaymentPageContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const methodParam = searchParams.get("method") as PaymentMethodCode | null;

  const [state, setState] = useState<CheckoutState | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedMethod, setSelectedMethod] = useState<PaymentMethodCode>("card");

  useEffect(() => {
    void fetchCheckoutState().then((response) => {
      setLoading(false);
      if (!response.ok) {
        setError(response.message);
        return;
      }
      setState(response.data);
      const authoritative = response.data.payment_method_code;
      setSelectedMethod(methodParam === "manual" || methodParam === "card" ? methodParam : authoritative);
    });
  }, [methodParam]);

  const methods = useMemo(
    () => [
      {
        code: "card" as const,
        canonical: "card",
        label: "Pay by Card",
        description: "Visa, Mastercard & UnionPay via AbhiPay",
        available: Boolean(state?.card_payment?.show_pay_button ?? state?.card_payment?.can_start),
        fee: null,
        currency: state?.pricing.currency ?? "PKR",
      },
      {
        code: "manual" as const,
        canonical: "manual",
        label: "Manual Payment",
        description: "Bank transfer / Easypaisa / JazzCash",
        available: true,
        fee: null,
        currency: state?.pricing.currency ?? "PKR",
      },
    ],
    [state],
  );

  if (loading) return <BookingLoadingState message="Loading payment…" />;
  if (!state?.ok) {
    return (
      <div className="mx-auto max-w-jp-booking p-8">
        <MissingBookingSessionState />
      </div>
    );
  }

  return (
    <BookingPageShell testId="payment-page">
      <BookingProgress steps={state.booking_session.progress} className="mb-6" compact />
      <BookingPageHeader
        title="Payment"
        description={`Reference: ${state.booking_reference ?? "—"}`}
      />

      <BookingLayout
        main={
          <BookingMainColumn>
            <BookingSection>
              <BookingSectionHeader title="Payment method" description="All transactions are secure and encrypted." />
              <PaymentMethodSelector
                methods={methods}
                selected={selectedMethod}
                onSelect={(code) => {
                  setSelectedMethod(code);
                  router.replace(`/booking/payment?method=${code}`, { scroll: false });
                }}
              />
            </BookingSection>

            <BookingSection data-testid="payment-method-content">
              {selectedMethod === "card" ? (
                <AbhiPayHandoffPanel state={state} />
              ) : (
                <ManualPaymentForm state={state} error={error} />
              )}
            </BookingSection>

            <p className="text-jp-xs text-jp-muted">
              Method selection reflects your checkout session. To change payment method before checkout, return to{" "}
              <Link href="/booking/review" className="font-semibold text-jp-primary">review</Link>.
            </p>
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
    </BookingPageShell>
  );
}

export function PaymentPage() {
  return (
    <Suspense fallback={<BookingLoadingState message="Loading payment…" />}>
      <PaymentPageContent />
    </Suspense>
  );
}
