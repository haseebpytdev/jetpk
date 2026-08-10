import { ReportsPageContent } from "@/features/reports/reports-page-content";

export const metadata = { title: "Booking Reports — JetPakistan Dashboard" };

export default function ReportsBookingsPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <ReportsPageContent searchParams={searchParams} module="bookings" />;
}
