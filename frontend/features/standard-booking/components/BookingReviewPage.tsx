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
import { ItineraryTimeline } from "../itinerary/ItineraryTimeline";

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

  const handleEditTravelers = () => {
    const url = context?.next_actions?.edit_passengers_url || "/booking/passengers";
    router.push(url);
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
        variant="flight-preview"
        testId="review-order-summary"
      />
      <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-3" data-testid="review-payment-method-sidebar">
        <h2 className="text-jp-sm font-semibold text-jp-text">Payment method</h2>
        <div className="mt-2">
          <PaymentMethodSelector
            methods={visibleMethods}
            selected={selectedMethod}
            onSelect={setSelectedMethod}
            disabled={context.submit_blocked || submitting}
            compact
          />
        </div>
      </div>
      {context.submit_blocked_reason ? (
        <p className="text-jp-sm text-amber-800 dark:text-amber-200" role="alert">{context.submit_blocked_reason}</p>
      ) : null}
      <PrimaryButton
        type="button"
        className="w-full"
        disabled={submitting || context.submit_blocked || expired}
        onClick={() => void handleSubmit()}
        data-testid="review-continue-button"
      >
        {submitting ? "Submitting…" : selectedMethod === "card" ? "Continue to payment" : "Confirm booking"}
      </PrimaryButton>
      <p className="text-jp-xs text-jp-muted">
        {selectedMethod === "card"
          ? "You will continue to our secure card payment step. No card details are collected on Review."
          : "No payment is taken on this step for manual payment."}
      </p>
    </>
  );

  return (
    <BookingPageShell testId="booking-review-page">
      <BookingProgress steps={context.booking_session.progress} className="mb-6" />

      <BookingPageHeader
        title="Review your booking"
        description="Confirm itinerary, travelers, contact details, and payment option before continuing."
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

      {error && error !== "missing_session" ? <p className="mt-4 text-jp-sm text-red-700 dark:text-red-300" role="alert">{error}</p> : null}

      <BookingLayout
        mobileSummary={<MobileOrderSummary label="Review summary">{summarySidebar}</MobileOrderSummary>}
        main={
          <BookingMainColumn>
            <BookingSection>
              <BookingSectionHeader title="Full itinerary" />
              <div data-testid="review-itinerary">
                <ItineraryTimeline itinerary={context.itinerary} />
              </div>
            </BookingSection>
            <BookingSection>
              <BookingSectionHeader
                title="Travelers"
                actions={
                  <button
                    type="button"
                    className="text-jp-sm font-semibold text-jp-primary hover:underline focus-visible:outline-none focus-visible:shadow-jp-focus"
                    onClick={handleEditTravelers}
                    data-testid="edit-traveler-details"
                  >
                    Edit traveler details
                  </button>
                }
              />
              <ReviewPassengerList
                passengers={context.passengers}
                documents={context.documents}
                onEdit={handleEditTravelers}
              />
            </BookingSection>
            <BookingSection>
              <BookingSectionHeader
                title="Contact details"
                actions={
                  <button
                    type="button"
                    className="text-jp-sm font-semibold text-jp-primary hover:underline focus-visible:outline-none focus-visible:shadow-jp-focus"
                    onClick={handleEditTravelers}
                    data-testid="edit-contact-details"
                  >
                    Edit
                  </button>
                }
              />
              <article className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid="review-contact">
                <dl className="space-y-2 text-jp-sm">
                  {context.contact.name ? (
                    <div className="flex justify-between gap-3">
                      <dt className="text-jp-muted">Contact name</dt>
                      <dd className="font-medium text-jp-text">{context.contact.name}</dd>
                    </div>
                  ) : null}
                  <div className="flex justify-between gap-3">
                    <dt className="text-jp-muted">Email</dt>
                    <dd className="font-medium text-jp-text">{context.contact.email || "—"}</dd>
                  </div>
                  <div className="flex justify-between gap-3">
                    <dt className="text-jp-muted">Mobile</dt>
                    <dd className="font-medium text-jp-text">{context.contact.phone || "—"}</dd>
                  </div>
                </dl>
              </article>
            </BookingSection>
          </BookingMainColumn>
        }
        sidebar={<BookingSidebar sticky>{summarySidebar}</BookingSidebar>}
      />
    </BookingPageShell>
  );
}
