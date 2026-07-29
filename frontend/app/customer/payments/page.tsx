import { CustomerPaymentsPage } from "@/features/customer-dashboard";
import { requireCustomerPortalAccess } from "@/features/auth/server/customer-portal-access";

export default async function CustomerPaymentsRoutePage() {
  const { session } = await requireCustomerPortalAccess();

  return (
    <CustomerPaymentsPage session={session} />
  );
}
