import { DashboardOverviewPage } from "@/features/customer-dashboard";
import { requireCustomerPortalAccess } from "@/features/auth/server/customer-portal-access";

export default async function CustomerDashboardPage() {
  const { session } = await requireCustomerPortalAccess();

  return (
    <DashboardOverviewPage session={session} />
  );
}
