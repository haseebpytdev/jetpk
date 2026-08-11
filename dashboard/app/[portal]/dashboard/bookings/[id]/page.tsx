import { BookingManagementPageContent } from "@/features/bookings/booking-management-page-content";

export async function generateMetadata({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return {
    title: `Booking ${id} — JetPakistan Dashboard`,
  };
}

export default async function BookingManagementPage({
  params,
}: {
  params: Promise<{ id: string; portal: string }>;
}) {
  const { id } = await params;
  return <BookingManagementPageContent bookingId={decodeURIComponent(id)} />;
}
