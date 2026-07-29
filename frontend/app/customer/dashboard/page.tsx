import { DashboardOverviewPage } from "@/features/customer-dashboard";
import { requireCustomerPortalAccess } from "@/features/auth/server/customer-portal-access";
import { PublicShell } from "@/components/layout/PublicShell";

export default async function CustomerDashboardPage() {
  const { session } = await requireCustomerPortalAccess();

  return (
    <PublicShell session={session}>
      <DashboardOverviewPage session={session} />
    </PublicShell>
  );
}
