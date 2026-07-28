import { AuditErrorShell, AuditModuleShell } from "@/features/audit/audit-module-shell";
import { parseAuditQuery } from "@/lib/audit-query";
import { AuditServiceError, getAuditModule } from "@/services/audit-service";
import {
  ForbiddenState,
  SanitizedErrorState,
  ServiceUnavailableState,
  UnauthorizedState,
} from "@/components/ui/data-source-status";
import { ReadOnlyServiceError } from "@/lib/read-only/read-only-service";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";

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
    return (
      <PageContainer>
        <PageHeader title="Audit" />
        <AuditModuleError error={e} />
      </PageContainer>
    );
  }
}

function AuditModuleError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="audit" />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  if (error instanceof AuditServiceError) {
    return <AuditErrorShell referenceId={error.referenceId} message={error.message} />;
  }
  throw error;
}
