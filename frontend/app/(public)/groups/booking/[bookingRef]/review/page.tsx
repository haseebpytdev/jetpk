import { GroupReviewPage } from "@/features/group-ticketing";

type PageProps = {
  params: Promise<{ bookingRef: string }>;
};

export default async function Page({ params }: PageProps) {
  const { bookingRef } = await params;
  return <GroupReviewPage bookingRef={bookingRef} />;
}
