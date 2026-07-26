import { ReportsPageContent } from "@/features/reports/reports-page-content";

export const metadata = { title: "Payment Reports — JetPakistan Admin Preview" };

export default function ReportsPaymentsPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <ReportsPageContent searchParams={searchParams} module="payments" />;
}
