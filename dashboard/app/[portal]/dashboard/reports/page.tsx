import { ReportsPageContent } from "@/features/reports/reports-page-content";

export const metadata = { title: "Reports — JetPakistan Dashboard" };

export default function ReportsPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <ReportsPageContent searchParams={searchParams} module="overview" />;
}
