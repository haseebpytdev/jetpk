import { SupportCaseDetailPage } from "@/features/customer-dashboard";
import { requireCustomerPortalAccess } from "@/features/auth/server/customer-portal-access";

type PageProps = {
  params: Promise<{ reference: string }>;
};

export default async function CustomerSupportDetailRoutePage({ params }: PageProps) {
  const { session } = await requireCustomerPortalAccess();
  const { reference } = await params;

  return <SupportCaseDetailPage session={session} reference={reference} />;
}
