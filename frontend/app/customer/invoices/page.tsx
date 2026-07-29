import { CustomerInvoicesPage } from "@/features/customer-dashboard";
import { requireCustomerPortalAccess } from "@/features/auth/server/customer-portal-access";
import { PublicShell } from "@/components/layout/PublicShell";

export default async function CustomerInvoicesRoutePage() {
  const { session } = await requireCustomerPortalAccess();

  return (
    <PublicShell session={session}>
      <CustomerInvoicesPage session={session} />
    </PublicShell>
  );
}
