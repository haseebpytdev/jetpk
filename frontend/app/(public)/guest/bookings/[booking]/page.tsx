import type { Metadata } from "next";
import { GuestBookingDetailPage } from "@/features/guest-booking/components/GuestBookingDetailPage";
import { noIndexMetadata } from "@/features/public-content";

type PageProps = {
  params: Promise<{ booking: string }>;
};

export const metadata: Metadata = noIndexMetadata("Guest booking — JetPakistan");


export default async function GuestBookingCleanAccessPage({ params }: PageProps) {
  const { booking } = await params;

  return <GuestBookingDetailPage bookingId={booking} />;
}
