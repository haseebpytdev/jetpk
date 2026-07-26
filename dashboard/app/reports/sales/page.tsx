import { ReportsPageContent } from "@/features/reports/reports-page-content";

export const metadata = { title: "Sales Reports — JetPakistan Admin Preview" };

export default function ReportsSalesPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <ReportsPageContent searchParams={searchParams} module="sales" />;
}
