import { CustomerNotificationsPage } from "@/features/customer-dashboard";
import { requireCustomerPortalAccess } from "@/features/auth/server/customer-portal-access";

export default async function CustomerNotificationsRoutePage() {
  const { session } = await requireCustomerPortalAccess();

  return (
    <CustomerNotificationsPage session={session} />
  );
}
