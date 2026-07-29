import { CustomerBookingsPage } from "@/features/customer-dashboard";
import { requireCustomerPortalAccess } from "@/features/auth/server/customer-portal-access";

export default async function CustomerBookingsRoutePage() {
  const { session } = await requireCustomerPortalAccess();

  return (
    <CustomerBookingsPage session={session} />
  );
}
