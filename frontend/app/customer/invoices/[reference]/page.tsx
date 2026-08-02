import { requireCustomerPortalAccess } from "@/features/auth/server/customer-portal-access";
import { CustomerInvoiceDetailPage } from "@/features/customer-dashboard";

type PageProps = {
  params: Promise<{ reference: string }>;
};

export default async function Page({ params }: PageProps) {
  const { session } = await requireCustomerPortalAccess();
  const { reference } = await params;
  return <CustomerInvoiceDetailPage session={session} reference={reference} />;
}
