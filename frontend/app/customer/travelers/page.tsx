import { requireCustomerPortalAccess } from "@/features/auth/server/customer-portal-access";
import { CustomerTravelersPage } from "@/features/customer-dashboard";

export default async function Page() {
  const { session } = await requireCustomerPortalAccess();
  return <CustomerTravelersPage session={session} />;
}
