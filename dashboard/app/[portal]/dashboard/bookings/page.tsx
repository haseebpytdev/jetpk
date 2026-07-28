import { BookingsPageContent } from "@/features/bookings/bookings-page-content";

export const metadata = {
  title: "Bookings — JetPakistan Admin Preview",
};

export default function BookingsPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <BookingsPageContent searchParams={searchParams} />;
}
