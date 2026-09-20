import { GuestBookingDetailPage } from "@/features/guest-booking/components/GuestBookingDetailPage";

type PageProps = {
  params: Promise<{ booking: string; token: string }>;
};

export default async function GuestBookingAccessRoutePage({ params }: PageProps) {
  const { booking, token } = await params;

  return <GuestBookingDetailPage bookingId={booking} token={token} />;
}
