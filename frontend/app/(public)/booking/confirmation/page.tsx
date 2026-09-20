import { BookingConfirmationPage } from "@/features/standard-booking/success/BookingConfirmationPage";
import type { Metadata } from "next";

export const metadata: Metadata = {
  robots: { index: false, follow: false },
};

export default function Page() {
  return <BookingConfirmationPage />;
}
