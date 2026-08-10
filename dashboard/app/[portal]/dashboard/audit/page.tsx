import { AuditPageContent } from "@/features/audit/audit-page-content";

export const metadata = { title: "Audit — JetPakistan Dashboard" };

export default function AuditPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <AuditPageContent searchParams={searchParams} />;
}
