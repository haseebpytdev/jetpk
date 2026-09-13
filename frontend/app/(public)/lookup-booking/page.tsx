import { BookingLookupPage } from "@/features/standard-booking/lookup/BookingLookupPage";
import { getBookingLookupHeroMedia } from "@/features/standard-booking/lookup/booking-lookup-media";

export const revalidate = 300;

export default async function Page() {
  const heroImage = await getBookingLookupHeroMedia();

  return <BookingLookupPage heroImage={heroImage} />;
}
