import { AuditErrorShell, AuditModuleShell } from "@/features/audit/audit-module-shell";
import { parseAuditQuery } from "@/lib/audit-query";
import { AuditServiceError, getAuditModule } from "@/services/audit-service";

type Props = {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
};

export async function AuditPageContent({ searchParams }: Props) {
  const sp = await searchParams;
  const query = parseAuditQuery(sp);

  try {
    const result = await getAuditModule(query);
    return <AuditModuleShell result={result} />;
  } catch (e) {
    if (e instanceof AuditServiceError) {
      return <AuditErrorShell referenceId={e.referenceId} message={e.message} />;
    }
    throw e;
  }
}
