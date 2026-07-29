"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { BookingStatusHero } from "@/features/standard-booking/components/BookingStatusHero";
import {
  BookingReferenceCard,
  BookingStatusCard,
  PaymentStatusCard,
  TicketingStatusCard,
} from "@/features/standard-booking/components/StatusCards";
import { ItineraryTimeline } from "@/features/standard-booking/itinerary/ItineraryTimeline";
import { PassengerSummary } from "@/features/standard-booking/components/PassengerSummary";
import { PostBookingActions } from "@/features/standard-booking/post-booking/PostBookingActions";
import { ReviewPriceBreakdown } from "@/features/standard-booking/components/ReviewPriceBreakdown";
import { statusToneClass } from "@/features/standard-booking/utils/status-presentation";
import type { BookingConfirmation } from "@/features/standard-booking/types/review-payment";
import { fetchCustomerBookingDetail } from "../services/customer-dashboard-api";
import { CustomerDashboardErrorState, CustomerDashboardShell } from "../shell/CustomerDashboardShell";
import type { PublicSession } from "@/types/session";

export function CustomerBookingDetailsPage({ session, reference }: { session: PublicSession; reference: string }) {
  const [data, setData] = useState<BookingConfirmation | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    setError(null);
    const result = await fetchCustomerBookingDetail(reference);
    if (!result.ok) {
      setError(result.status === 403 ? "You do not have access to this booking." : result.message);
      setData(null);
    } else {
      setData(result.data);
    }
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, [reference]);

  return (
    <CustomerDashboardShell session={session} title={`Booking ${reference}`}>
      {loading ? <p className="text-jp-sm text-jp-muted">Loading booking…</p> : null}
      {error ? <CustomerDashboardErrorState message={error} onRetry={load} /> : null}
      {data?.ok ? (
        <div data-testid="customer-booking-detail">
          <div className={`rounded-jp-lg border p-1 ${statusToneClass(data.presentation.tone)}`}>
            <BookingStatusHero presentation={data.presentation} bookingReference={data.booking_reference} />
          </div>

          <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <PaymentStatusCard status={data.payment_status} />
            <BookingStatusCard status={data.booking_status} />
            <TicketingStatusCard status={data.ticketing_status} />
          </div>

          <div className="mt-6 grid gap-6 lg:grid-cols-[2fr_1fr]">
            <div className="space-y-6">
              <ItineraryTimeline itinerary={data.itinerary} />
              <PassengerSummary passengers={data.passengers} tickets={data.tickets} />
            </div>
            <aside className="space-y-4">
              <BookingReferenceCard
                bookingReference={data.booking_reference}
                pnr={data.pnr_details.booking_reference}
                airlineLocator={data.pnr_details.airline_locator}
              />
              <ReviewPriceBreakdown pricing={data.pricing} />
              <PostBookingActions actions={data.actions} />
              <Link href="/customer/bookings" className="text-jp-sm text-jp-primary">
                Back to bookings
              </Link>
            </aside>
          </div>
        </div>
      ) : null}
    </CustomerDashboardShell>
  );
}
