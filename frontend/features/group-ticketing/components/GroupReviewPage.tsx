"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { BookingProgress } from "@/features/booking-progress";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { confirmGroupReview, fetchGroupReview } from "../services/group-ticketing-api";
import type { GroupBookingReview } from "../types";
import { GroupHoldExpiredState } from "./GroupStateCards";
import { GroupBookingSummaryCard } from "./GroupPackageBlocks";
import {
  GroupCheckoutDecisionDialog,
  type GroupCheckoutDecisionModal,
} from "./GroupCheckoutDecisionDialog";

type GroupReviewPageProps = {
  bookingRef: string;
};

export function GroupReviewPage({ bookingRef }: GroupReviewPageProps) {
  const router = useRouter();
  const [booking, setBooking] = useState<GroupBookingReview | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [loading, setLoading] = useState(true);
  const [decisionModal, setDecisionModal] = useState<GroupCheckoutDecisionModal | null>(null);
  const [acceptFareChange, setAcceptFareChange] = useState(false);

  useEffect(() => {
    void fetchGroupReview(bookingRef).then((response) => {
      setLoading(false);
      if (!response.ok) {
        if (response.status === 401) {
          router.push(`/login?redirect=${encodeURIComponent(`/groups/booking/${bookingRef}/review`)}`);
          return;
        }
        setError(response.message);
        return;
      }
      setBooking(response.data);
    });
  }, [bookingRef, router]);

  const handleConfirm = async (acceptedFare = acceptFareChange) => {
    setSubmitting(true);
    setError(null);
    const response = await confirmGroupReview(bookingRef, { acceptFareChange: acceptedFare });
    setSubmitting(false);
    if (!response.ok) {
      const decision = (response.data as { checkout_decision?: { modal?: GroupCheckoutDecisionModal } } | undefined)
        ?.checkout_decision;
      if (decision?.modal) {
        setDecisionModal(decision.modal);
      }
      setError(response.message);
      return;
    }
    router.push(response.data.redirect_path);
  };

  if (loading) return <p className="p-8 text-jp-sm text-jp-muted">Loading review…</p>;
  if (error?.toLowerCase().includes("expired")) return <div className="p-8"><GroupHoldExpiredState /></div>;
  if (!booking) return <p className="p-8 text-jp-sm text-red-700">{error ?? "Booking not found."}</p>;

  return (
    <div className="mx-auto w-full max-w-jp-container px-jp-xl py-8 font-[Inter,system-ui,sans-serif]">
      <BookingProgress steps={booking.progress} className="mb-6" />
      <h1 className="text-2xl font-semibold text-jp-text">Review your booking</h1>
      <p className="mt-1 text-jp-sm text-jp-muted">Reference: {booking.reference}</p>

      <div className="mt-6 grid gap-6 lg:grid-cols-[2fr_1fr]">
        <section className="space-y-4">
          <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
            <h2 className="text-jp-base font-semibold">{booking.inventory.airline_name}</h2>
            <p className="text-jp-sm">{booking.inventory.route_line}</p>
            <p className="text-jp-sm text-jp-muted">{booking.inventory.departure_date_short}</p>
            {booking.inventory.baggage_line ? <p className="mt-2 text-jp-sm">{booking.inventory.baggage_line}</p> : null}
            {booking.seat_selection?.message ? <p className="mt-2 text-jp-sm text-jp-muted">{booking.seat_selection.message}</p> : null}
          </div>

          <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
            <h2 className="text-jp-base font-semibold">Passengers</h2>
            <ul className="mt-2 space-y-2 text-jp-sm">
              {booking.passengers.map((passenger, index) => (
                <li key={index}>{passenger.full_name ?? `${passenger.first_name} ${passenger.last_name}`}</li>
              ))}
            </ul>
            <p className="mt-3 text-jp-sm text-jp-muted">
              Contact: {booking.contact.name} · {booking.contact.email} · {booking.contact.phone}
            </p>
          </div>

          <p className="text-jp-sm text-jp-muted">{booking.hold_notice}</p>
          <p className="text-jp-sm text-jp-muted">{booking.manual_payment_notice}</p>
          {error ? <p className="text-jp-sm text-red-700" role="alert">{error}</p> : null}
          <PrimaryButton onClick={() => void handleConfirm()} disabled={submitting}>
            {submitting ? "Confirming…" : "Confirm checkout intent"}
          </PrimaryButton>
        </section>

        <aside>
          <GroupBookingSummaryCard
            package={booking.inventory}
            seatCount={booking.seat_count}
            totalFormatted={booking.total_formatted}
          />
        </aside>
      </div>

      <GroupCheckoutDecisionDialog
        open={Boolean(decisionModal)}
        modal={decisionModal}
        onPrimary={
          decisionModal?.primary_action?.toLowerCase().includes("accept")
            ? () => {
                setAcceptFareChange(true);
                setDecisionModal(null);
                void handleConfirm(true);
              }
            : decisionModal?.primary_action
              ? () => {
                  setDecisionModal(null);
                  router.push(`/groups/${encodeURIComponent(booking.inventory.public_id)}/passengers`);
                }
              : undefined
        }
        onSecondary={() => {
          setDecisionModal(null);
          router.push("/groups/search");
        }}
      />
    </div>
  );
}
