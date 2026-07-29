import { CustomerProfilePage } from "@/features/customer-dashboard";
import { requireCustomerPortalAccess } from "@/features/auth/server/customer-portal-access";

export default async function CustomerProfileRoutePage() {
  const { session } = await requireCustomerPortalAccess();

  return (
    <CustomerProfilePage session={session} />
  );
}
