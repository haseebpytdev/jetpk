import { redirect } from "next/navigation";
import type { Metadata } from "next";
import { noIndexMetadata } from "@/features/public-content";

type PageProps = {
  params: Promise<{ booking: string; token: string }>;
};

export const metadata: Metadata = noIndexMetadata("Guest booking — JetPakistan");


/**
 * Legacy token-in-path entry: hand off to Laravel exchange so the raw token
 * leaves the address bar after one redirect (OPS-DEF-0011).
 */
export default async function GuestBookingLegacyAccessPage({ params }: PageProps) {
  const { booking, token } = await params;
  const safeBooking = encodeURIComponent(booking);
  const safeToken = encodeURIComponent(token);

  redirect(`/laravel/guest/bookings/${safeBooking}/access/${safeToken}`);
}
