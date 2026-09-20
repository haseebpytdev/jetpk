import type { Metadata } from "next";
import { BookingLookupPage } from "@/features/standard-booking/lookup/BookingLookupPage";

export const metadata: Metadata = {
  title: "Manage booking",
  robots: { index: false, follow: true },
};

export default function Page() {
  return <BookingLookupPage />;
}
