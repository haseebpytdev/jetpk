import { CustomerSupportPage } from "@/features/customer-dashboard";
import { requireCustomerPortalAccess } from "@/features/auth/server/customer-portal-access";

export default async function CustomerSupportRoutePage() {
  const { session } = await requireCustomerPortalAccess();

  return (
    <CustomerSupportPage session={session} />
  );
}
