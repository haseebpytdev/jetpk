import { CustomerSecurityPage } from "@/features/customer-dashboard";
import { requireCustomerPortalAccess } from "@/features/auth/server/customer-portal-access";

export default async function CustomerSecurityRoutePage() {
  const { session } = await requireCustomerPortalAccess();

  return (
    <CustomerSecurityPage session={session} />
  );
}
