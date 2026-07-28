import { ReportsModuleShell, ReportsErrorShell } from "@/features/reports/reports-module-shell";
import { parseReportsQuery } from "@/lib/reports-query";
import { getReportModule, ReportsServiceError } from "@/services/report-service";
import type { ReportsModuleKey } from "@/types/report";
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
  module: ReportsModuleKey;
};

export async function ReportsPageContent({ searchParams, module }: Props) {
  const sp = await searchParams;
  const query = parseReportsQuery(sp);

  try {
    const result = await getReportModule(query, module);
    return <ReportsModuleShell module={module} result={result} />;
  } catch (e) {
    return (
      <PageContainer>
        <PageHeader title="Reports" />
        <ReportsModuleError error={e} />
      </PageContainer>
    );
  }
}

function ReportsModuleError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="reports" />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  if (error instanceof ReportsServiceError) {
    return <ReportsErrorShell referenceId={error.referenceId} message={error.message} />;
  }
  throw error;
}
