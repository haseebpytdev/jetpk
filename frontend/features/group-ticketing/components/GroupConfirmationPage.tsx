"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { BookingProgress } from "@/features/booking-progress";
import { fetchGroupConfirmation } from "../services/group-ticketing-api";
import type { GroupBookingConfirmation } from "../types";
import { GroupBookingErrorState } from "./GroupStateCards";

type GroupConfirmationPageProps = {
  bookingRef: string;
};

export function GroupBookingConfirmationPage({ bookingRef }: GroupConfirmationPageProps) {
  const router = useRouter();
  const [booking, setBooking] = useState<GroupBookingConfirmation | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    void fetchGroupConfirmation(bookingRef).then((response) => {
      setLoading(false);
      if (!response.ok) {
        if (response.status === 401) {
          router.push(`/login?redirect=${encodeURIComponent(`/groups/booking/${bookingRef}/confirmation`)}`);
          return;
        }
        setError(response.message);
        return;
      }
      setBooking(response.data);
    });
  }, [bookingRef, router]);

  if (loading) return <p className="p-8 text-jp-sm text-jp-muted">Loading confirmation…</p>;
  if (!booking) {
    return (
      <div className="p-8">
        <GroupBookingErrorState title="Booking unavailable" message={error ?? "This booking session is not available."} />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-3xl px-4 py-8 print:px-0">
      <BookingProgress steps={booking.progress} className="mb-6" />
      <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-6 text-center print:border-0 print:shadow-none">
        <h1 className="text-2xl font-semibold text-jp-text">{booking.hero.title}</h1>
        <p className="mt-2 text-jp-sm text-jp-muted">{booking.hero.subtitle}</p>
        <p className="mt-4 text-jp-xs uppercase tracking-wide text-jp-muted">Booking reference</p>
        <p className="text-xl font-semibold tracking-wide text-jp-text" data-testid="group-booking-reference">{booking.reference}</p>
        <p className="mt-3 inline-flex rounded-jp-pill bg-jp-primary-soft px-3 py-1 text-jp-sm font-medium text-jp-primary">{booking.hero.status_label}</p>
        <dl className="mt-6 grid gap-3 text-left text-jp-sm sm:grid-cols-2">
          <div><dt className="text-jp-muted">Payment status</dt><dd>{booking.payment_status_label}</dd></div>
          <div><dt className="text-jp-muted">Total</dt><dd>{booking.currency} {booking.total_formatted}</dd></div>
          <div><dt className="text-jp-muted">Passengers</dt><dd>{booking.seat_count}</dd></div>
          <div><dt className="text-jp-muted">Route</dt><dd>{booking.inventory.route_line}</dd></div>
        </dl>
        <div className="mt-6 flex flex-wrap justify-center gap-3 print:hidden">
          <Link href="/support" className="rounded-jp-button border border-jp-border px-4 py-2 text-jp-sm font-semibold">Contact support</Link>
          <Link href="/groups/search" className="rounded-jp-button bg-jp-primary px-4 py-2 text-jp-sm font-semibold text-white">Search more group fares</Link>
        </div>
      </section>
    </div>
  );
}
