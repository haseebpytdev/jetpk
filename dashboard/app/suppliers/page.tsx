import { SuppliersPageContent } from "@/features/suppliers/suppliers-page-content";

export const metadata = {
  title: "Suppliers — JetPakistan Admin Preview",
};

export default function SuppliersPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <SuppliersPageContent searchParams={searchParams} />;
}
