"use client";

import Link from "next/link";
import { BookingProgress } from "@/features/booking-progress";
import {
  BookingLayout,
  BookingLoadingState,
  BookingMainColumn,
  BookingPageShell,
  BookingSidebar,
  OrderSummary,
} from "@/features/booking-layout";
import { useBookingStatusPoll } from "../hooks/useBookingStatusPoll";
import type { BookingConfirmation } from "../types/review-payment";
import { MissingBookingSessionState } from "../components/BookingStateCards";
import { BookingStatusHero } from "../components/BookingStatusHero";
import {
  BookingReferenceCard,
  BookingStatusCard,
  PaymentStatusCard,
  TicketingStatusCard,
} from "../components/StatusCards";
import { ItineraryTimeline } from "../itinerary/ItineraryTimeline";
import { PassengerSummary } from "../components/PassengerSummary";
import { PostBookingActions } from "../post-booking/PostBookingActions";
import { statusToneClass } from "../utils/status-presentation";

function isConfirmation(payload: BookingConfirmation | null): payload is BookingConfirmation {
  return Boolean(payload && "presentation" in payload);
}

export function BookingConfirmationPage() {
  const { data, loading, error } = useBookingStatusPoll({ mode: "confirmation" });
  const confirmation = isConfirmation(data as BookingConfirmation | null) ? (data as BookingConfirmation) : null;

  if (loading) return <BookingLoadingState message="Loading confirmation…" />;
  if (!confirmation?.ok) {
    return (
      <div className="mx-auto max-w-jp-booking p-8">
        <MissingBookingSessionState />
        {error ? <p className="mt-4 text-jp-sm text-red-700 dark:text-red-300" role="alert">{error}</p> : null}
      </div>
    );
  }

  const presentation = confirmation.presentation;

  return (
    <BookingPageShell testId="booking-confirmation-page">
      <BookingProgress steps={confirmation.booking_session.progress} className="mb-6 print:hidden" />

      <div className={`rounded-jp-lg border p-1 ${statusToneClass(presentation.tone)}`}>
        <BookingStatusHero presentation={presentation} bookingReference={confirmation.booking_reference} />
      </div>

      <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <PaymentStatusCard status={confirmation.payment_status} />
        <BookingStatusCard status={confirmation.booking_status} />
        <TicketingStatusCard status={confirmation.ticketing_status} />
      </div>

      <BookingLayout
        className="mt-6"
        main={
          <BookingMainColumn>
            <ItineraryTimeline itinerary={confirmation.itinerary} />
            <PassengerSummary passengers={confirmation.passengers} tickets={confirmation.tickets} />
            {confirmation.supplier_notice ? (
              <p className="rounded-jp-md border border-jp-border bg-jp-surface-muted p-3 text-jp-sm text-jp-muted" role="note">
                {confirmation.supplier_notice}
              </p>
            ) : null}
            {confirmation.cancellation.request_pending || confirmation.cancellation.already_cancelled ? (
              <p className="rounded-jp-md border border-jp-border bg-jp-surface-muted p-3 text-jp-sm" role="status">
                {confirmation.cancellation.message}
              </p>
            ) : null}
            {confirmation.refund.available ? (
              <p className="rounded-jp-md border border-jp-border bg-jp-surface-muted p-3 text-jp-sm" role="status" data-testid="refund-status">
                Refund status: {confirmation.refund.label}
              </p>
            ) : null}
          </BookingMainColumn>
        }
        sidebar={
          <BookingSidebar sticky>
            <BookingReferenceCard
              bookingReference={confirmation.booking_reference}
              pnr={confirmation.pnr_details.booking_reference}
              airlineLocator={confirmation.pnr_details.airline_locator}
            />
            <OrderSummary pricing={confirmation.pricing} />
            <PostBookingActions actions={confirmation.actions} />
            <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 text-jp-sm print:hidden">
              <p className="font-semibold">Need help?</p>
              <Link href={confirmation.support.support_url ?? "/support"} className="mt-2 block text-jp-primary">
                Contact support
              </Link>
              <Link href={confirmation.support.lookup_url ?? "/lookup-booking"} className="mt-1 block text-jp-primary">
                Lookup booking
              </Link>
              <Link href="/booking/invoice" className="mt-1 block text-jp-primary">
                View invoice
              </Link>
            </div>
          </BookingSidebar>
        }
      />
    </BookingPageShell>
  );
}
