import { ReportsModuleShell, ReportsErrorShell } from "@/features/reports/reports-module-shell";
import { parseReportsQuery } from "@/lib/reports-query";
import { getReportModule, ReportsServiceError } from "@/services/report-service";
import type { ReportsModuleKey } from "@/types/report";

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
    if (e instanceof ReportsServiceError) {
      return <ReportsErrorShell referenceId={e.referenceId} message={e.message} />;
    }
    throw e;
  }
}
