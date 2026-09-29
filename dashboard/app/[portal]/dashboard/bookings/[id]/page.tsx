import { BookingDetailPageContent } from "@/features/bookings/booking-detail-page-content";

export async function generateMetadata({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return {
    title: `Booking ${id} — JetPakistan Dashboard`,
  };
}

export default async function BookingDetailPage({
  params,
}: {
  params: Promise<{ id: string; portal: string }>;
}) {
  const { id } = await params;
  return <BookingDetailPageContent bookingId={decodeURIComponent(id)} />;
}
