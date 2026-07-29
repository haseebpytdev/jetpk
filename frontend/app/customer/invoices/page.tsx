import { CustomerInvoicesPage } from "@/features/customer-dashboard";
import { requireCustomerPortalAccess } from "@/features/auth/server/customer-portal-access";

export default async function CustomerInvoicesRoutePage() {
  const { session } = await requireCustomerPortalAccess();

  return (
    <CustomerInvoicesPage session={session} />
  );
}
