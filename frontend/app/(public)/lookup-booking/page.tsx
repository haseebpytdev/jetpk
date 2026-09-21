import type { Metadata } from "next";
import { BookingLookupPage } from "@/features/standard-booking/lookup/BookingLookupPage";
import { noIndexMetadata } from "@/features/public-content";

export const metadata: Metadata = noIndexMetadata("Manage booking — JetPakistan", {
  description: "Look up an existing JetPakistan booking with your reference and contact details.",
  path: "/lookup-booking",
  follow: true,
});

export default function Page() {
  return <BookingLookupPage />;
}
