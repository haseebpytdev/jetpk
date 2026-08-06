"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { PageContainer } from "@/components/layout/PageContainer";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { BookingStatusHero } from "@/features/standard-booking/components/BookingStatusHero";
import {
  BookingReferenceCard,
  BookingStatusCard,
  PaymentStatusCard,
  TicketingStatusCard,
} from "@/features/standard-booking/components/StatusCards";
import { ItineraryTimeline } from "@/features/standard-booking/itinerary/ItineraryTimeline";
import { PassengerSummary } from "@/features/standard-booking/components/PassengerSummary";
import { ReviewPriceBreakdown } from "@/features/standard-booking/components/ReviewPriceBreakdown";
import { statusToneClass } from "@/features/standard-booking/utils/status-presentation";
import {
  fetchGuestBookingDetail,
  guestApiErrorMessage,
  type GuestBookingDetailPayload,
} from "../services/guest-booking-api";
import { GuestCancellationPanel } from "./GuestCancellationPanel";
import { GuestPaymentProofPanel } from "./GuestPaymentProofPanel";

type GuestBookingDetailPageProps = {
  bookingId: string;
  token: string;
};

export function GuestBookingDetailPage({ bookingId, token }: GuestBookingDetailPageProps) {
  const [data, setData] = useState<GuestBookingDetailPayload | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    setError(null);
    const result = await fetchGuestBookingDetail(bookingId, token);
    if (!result.ok) {
      setError(guestApiErrorMessage(result));
      setData(null);
    } else {
      setData(result.data);
    }
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, [bookingId, token]);

  const passengerRows =
    data?.passengers?.map((passenger) => ({
      passenger_type: passenger.passenger_type,
      first_name: passenger.display_name,
      last_name: "",
      passport_number_masked: passenger.passport_number_masked,
      national_id_masked: passenger.national_id_masked,
    })) ?? [];

  return (
    <PageContainer className="py-jp-xl">
      {loading ? (
        <p className="text-jp-sm text-jp-muted" data-testid="guest-booking-loading">Loading booking…</p>
      ) : null}

      {error ? (
        <div
          className="rounded-jp-lg border border-jp-danger/30 bg-jp-danger/10 p-4 text-jp-sm"
          role="alert"
          data-testid="guest-booking-access-error"
        >
          <p>{error}</p>
          <Link href="/lookup-booking" className="mt-3 inline-flex text-jp-primary">Try lookup again</Link>
        </div>
      ) : null}

      {data?.ok ? (
        <div data-testid="guest-booking-detail-page">
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
              <PassengerSummary passengers={passengerRows} tickets={data.tickets} />
              {data.contact ? (
                <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid="guest-contact-masked">
                  <h2 className="text-jp-base font-semibold text-jp-text">Contact</h2>
                  <p className="mt-2 text-jp-sm text-jp-muted">Email: {data.contact.email_masked ?? "—"}</p>
                  <p className="text-jp-sm text-jp-muted">Phone: {data.contact.phone_masked ?? "—"}</p>
                </section>
              ) : null}
            </div>

            <aside className="space-y-4">
              <BookingReferenceCard
                bookingReference={data.booking_reference}
                pnr={data.pnr_details?.available ? data.pnr_details.booking_reference : undefined}
                airlineLocator={data.pnr_details?.airline_locator}
              />
              <ReviewPriceBreakdown pricing={data.pricing} />
              {data.capabilities?.can_upload_payment_proof && data.capabilities.mutation_urls?.payment_proof ? (
                <GuestPaymentProofPanel
                  submitUrl={data.capabilities.mutation_urls.payment_proof}
                  balanceDue={data.pricing.total}
                  currency={data.pricing.currency}
                  onSubmitted={load}
                />
              ) : null}
              {data.cancellation ? (
                <GuestCancellationPanel
                  canRequest={data.capabilities?.can_request_cancellation ?? false}
                  summary={data.cancellation}
                  submitUrl={data.capabilities?.mutation_urls?.request_cancellation ?? null}
                  onSubmitted={load}
                />
              ) : null}
              {data.capabilities?.blade_fallback_urls?.abhipay_start ? (
                <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid="guest-abhipay-blade-handoff">
                  <h2 className="text-jp-base font-semibold text-jp-text">Pay by card</h2>
                  <p className="mt-2 text-jp-sm text-jp-muted">
                    Card payments are handled on our secure Blade checkout surface.
                  </p>
                  <a
                    href={data.capabilities.blade_fallback_urls.abhipay_start}
                    className="mt-3 inline-flex text-jp-sm font-semibold text-jp-primary"
                  >
                    Continue to secure card payment
                  </a>
                </section>
              ) : null}
              {data.blade_fallback_url ? (
                <Link
                  href={data.blade_fallback_url}
                  className="text-jp-sm text-jp-primary"
                  data-testid="guest-blade-fallback-link"
                >
                  View secure Blade booking page
                </Link>
              ) : null}
              <Link href="/lookup-booking" className="text-jp-sm text-jp-primary">Look up another booking</Link>
            </aside>
          </div>
        </div>
      ) : null}
    </PageContainer>
  );
}
