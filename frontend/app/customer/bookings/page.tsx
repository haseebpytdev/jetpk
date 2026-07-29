import { CustomerPortalPlaceholder } from "@/features/auth/components/CustomerPortalPlaceholder";
import { requireCustomerPortalAccess } from "@/features/auth/server/customer-portal-access";

export default async function CustomerBookingsPlaceholderPage() {
  const { session } = await requireCustomerPortalAccess();

  return <CustomerPortalPlaceholder session={session} title="My bookings" />;
}
