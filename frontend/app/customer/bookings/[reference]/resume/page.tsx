import { CustomerDraftResumeClient } from "@/features/customer-dashboard/bookings/CustomerDraftResumeClient";
import { requireCustomerPortalAccess } from "@/features/auth/server/customer-portal-access";

type PageProps = {
  params: Promise<{ reference: string }>;
};

export default async function CustomerBookingResumeRoutePage({ params }: PageProps) {
  await requireCustomerPortalAccess();
  const { reference } = await params;

  return <CustomerDraftResumeClient reference={reference} />;
}
