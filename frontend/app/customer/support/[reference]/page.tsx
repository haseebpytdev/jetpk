import { SupportCaseDetailPage } from "@/features/customer-dashboard";
import { requireCustomerPortalAccess } from "@/features/auth/server/customer-portal-access";
import { PublicShell } from "@/components/layout/PublicShell";

type PageProps = {
  params: Promise<{ reference: string }>;
};

export default async function CustomerSupportDetailRoutePage({ params }: PageProps) {
  const { session } = await requireCustomerPortalAccess();
  const { reference } = await params;

  return (
    <PublicShell session={session}>
      <SupportCaseDetailPage session={session} reference={reference} />
    </PublicShell>
  );
}
