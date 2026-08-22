"use client";

import { useCallback, useEffect, useRef, useState } from "react";
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
  MobileOrderSummary,
  OrderSummary,
} from "@/features/booking-layout";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { BookingSessionCountdown } from "./BookingSessionCountdown";
import {
  BookingSessionExpiredState,
  MissingBookingSessionState,
  OfferExpiredState,
} from "./BookingStateCards";
import {
  acceptUpdatedFare,
  declineUpdatedFare,
  fetchBookingReview,
  submitBookingReview,
} from "../services/booking-checkout-api";
import type { BookingReviewContext, PaymentMethodCode, ReviewPaymentMethod } from "../types/review-payment";
import { resolveBookingNextUrl } from "../utils/allowlist";
import { ReviewPassengerList } from "./ReviewPassengerList";
import { PaymentMethodSelector } from "./PaymentMethodSelector";

export function BookingReviewPage() {
  const router = useRouter();
  const [context, setContext] = useState<BookingReviewContext | null>(null);
  const [selectedMethod, setSelectedMethod] = useState<PaymentMethodCode>("manual");
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [expired, setExpired] = useState(false);
  const submitLock = useRef(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    const response = await fetchBookingReview();
    setLoading(false);

    if (!response.ok) {
      const dataStatus = (response.data as { status?: string } | undefined)?.status;
      if (response.status === 404 || response.status === 0 || response.status >= 500 || dataStatus === "missing_session") {
        setError("missing_session");
      } else {
        setError(response.message);
      }
      return;
    }

    if (!response.data.ok) {
      setError("Unable to load review.");
      return;
    }

    setContext(response.data);
    const defaultMethod = response.data.payment_methods.find((m) => m.available)?.code ?? "manual";
    setSelectedMethod(defaultMethod);
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const handleSubmit = async () => {
    if (!context || context.submit_blocked || submitLock.current) return;
    const method = context.payment_methods.find((m) => m.code === selectedMethod);
    if (!method?.available) return;

    submitLock.current = true;
    setSubmitting(true);
    setError(null);

    const response = await submitBookingReview(method.canonical);
    setSubmitting(false);
    submitLock.current = false;

    if (!response.ok) {
      const data = response.data as { status?: string; fare_change?: BookingReviewContext["fare_change"] } | undefined;
      if (response.status === 409 || data?.status === "fare_changed") {
        setContext((current) => (current ? { ...current, fare_change: data?.fare_change ?? current.fare_change, submit_blocked: true } : current));
        setError(response.message);
        return;
      }
      setError(response.message);
      return;
    }

    const next = resolveBookingNextUrl(response.data.next_url ?? "");
    if (!next) {
      setError("Invalid next step from server.");
      return;
    }
    router.push(next);
  };

  const handleAcceptFare = async () => {
    const acceptUrl = context?.next_actions?.accept_fare_url;
    const match = acceptUrl?.match(/\/booking\/(\d+)\/accept-updated-fare/);
    if (!match) return;
    const response = await acceptUpdatedFare(Number(match[1]));
    if (!response.ok) {
      setError(response.message);
      return;
    }
    await load();
  };

  const handleDeclineFare = async () => {
    const declineUrl = context?.next_actions?.decline_fare_url;
    const match = declineUrl?.match(/\/booking\/(\d+)\/decline-updated-fare/);
    if (!match) return;
    await declineUpdatedFare(Number(match[1]));
    router.push("/flights/results");
  };

  if (loading) return <BookingLoadingState message="Loading review…" testId="review-loading" />;
  if (error === "missing_session") return <div className="mx-auto max-w-jp-booking p-8"><MissingBookingSessionState /></div>;
  if (expired) return <div className="mx-auto max-w-jp-booking p-8"><BookingSessionExpiredState /></div>;
  if (!context) return <div className="mx-auto max-w-jp-booking p-8"><OfferExpiredState /></div>;

  const visibleMethods: ReviewPaymentMethod[] = context.payment_methods.filter((m) => m.code === "manual" || m.code === "card");

  const summarySidebar = (
    <>
      <OrderSummary
        itinerary={context.itinerary}
        travellerTotal={context.passengers.length}
        pricing={context.pricing}
      />
      <PaymentMethodSelector
        methods={visibleMethods}
        selected={selectedMethod}
        onSelect={setSelectedMethod}
        disabled={context.submit_blocked || submitting}
      />
      {context.submit_blocked_reason ? (
        <p className="text-jp-sm text-amber-800 dark:text-amber-200" role="alert">{context.submit_blocked_reason}</p>
      ) : null}
      {error && error !== "missing_session" ? <p className="text-jp-sm text-red-700 dark:text-red-300" role="alert">{error}</p> : null}
      <PrimaryButton
        type="button"
        className="w-full"
        disabled={submitting || context.submit_blocked || expired}
        onClick={() => void handleSubmit()}
        data-testid="review-continue-button"
      >
        {submitting ? "Submitting…" : selectedMethod === "card" ? "Continue to payment" : "Confirm booking"}
      </PrimaryButton>
      <p className="text-jp-xs text-jp-muted">No payment is taken on this step for manual payment.</p>
    </>
  );

  return (
    <BookingPageShell testId="booking-review-page">
      <BookingProgress steps={context.booking_session.progress} className="mb-6" />

      <BookingPageHeader
        title="Review & confirm booking"
        description="Confirm itinerary, travellers, and payment option before submitting."
        actions={
          <BookingSessionCountdown
            expiresAt={context.booking_session.expires_at}
            serverTime={context.booking_session.server_time}
            onExpired={() => setExpired(true)}
          />
        }
      />

      {context.notices.map((notice) => (
        <p key={notice} className="mt-4 rounded-jp-md border border-jp-border bg-jp-surface-muted p-3 text-jp-sm" role="status">{notice}</p>
      ))}

      {context.fare_change?.requires_acceptance ? (
        <div className="mt-4 rounded-jp-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30" data-testid="fare-change-panel" role="alert">
          <h2 className="text-jp-base font-semibold">Fare updated</h2>
          <p className="mt-1 text-jp-sm text-jp-muted">
            Previous total: {context.fare_change.old_total_formatted ?? context.fare_change.old_total}
            {" · "}
            New total: {context.fare_change.new_total_formatted ?? context.fare_change.new_total}
          </p>
          <div className="mt-3 flex flex-wrap gap-2">
            <PrimaryButton type="button" onClick={() => void handleAcceptFare()}>Accept updated fare</PrimaryButton>
            <button type="button" className="rounded-jp-md border border-jp-border px-4 py-2 text-jp-sm" onClick={() => void handleDeclineFare()}>
              Return to results
            </button>
          </div>
        </div>
      ) : null}

      <BookingLayout
        mobileSummary={<MobileOrderSummary label="Review summary">{summarySidebar}</MobileOrderSummary>}
        main={
          <BookingMainColumn>
            <BookingSection>
              <BookingSectionHeader title="Itinerary" />
              <OrderSummary itinerary={context.itinerary} travellerTotal={context.passengers.length} collapsed />
            </BookingSection>
            <BookingSection>
              <BookingSectionHeader title="Travelers" />
              <ReviewPassengerList passengers={context.passengers} documents={context.documents} />
            </BookingSection>
            <BookingSection>
              <BookingSectionHeader title="Contact" />
              <p className="text-jp-sm text-jp-muted">{context.contact.email}</p>
              <p className="text-jp-sm text-jp-muted">{context.contact.phone}</p>
            </BookingSection>
          </BookingMainColumn>
        }
        sidebar={<BookingSidebar>{summarySidebar}</BookingSidebar>}
      />
    </BookingPageShell>
  );
}
