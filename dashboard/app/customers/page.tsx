import { CustomersPageContent } from "@/features/customers/customers-page-content";

export const metadata = {
  title: "Customers — JetPakistan Admin Preview",
};

export default function CustomersPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <CustomersPageContent searchParams={searchParams} />;
}
