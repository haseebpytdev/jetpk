import { Breadcrumb, PageContainer, PageHeader, PreviewDataBanner } from "@/components/ui/page-layout";
import { Skeleton } from "@/components/ui/skeleton";
import { ErrorState } from "@/components/ui/error-state";
import { AuditWorkspace } from "@/features/audit/audit-workspace";
import type { AuditModuleResult } from "@/types/audit";

type Props = {
  result: AuditModuleResult;
};

export function AuditModuleShell({ result }: Props) {
  return (
    <PageContainer>
      <PageHeader
        breadcrumb={
          <Breadcrumb items={[{ label: "Home" }, { label: "Insights & system" }, { label: "Audit" }]} />
        }
        title="Audit"
        description="Audit event directory — synthetic preview events only. No live persistence."
      />
      <PreviewDataBanner />

      <div role="status" className="rounded-2xl border border-blue-200 bg-blue-50/60 px-4 py-3 text-sm text-blue-900">
        Dashboard preview only — audit events are synthetic and do not record live mutations.
      </div>

      {result.state === "loading" ? (
        <div aria-busy="true" aria-label="Loading audit" data-testid="audit-loading-state">
          <Skeleton className="h-24 w-full" />
          <Skeleton className="mt-4 h-40 w-full" />
        </div>
      ) : (
        <AuditWorkspace result={result} />
      )}
    </PageContainer>
  );
}

export function AuditErrorShell({ referenceId, message }: { referenceId: string; message: string }) {
  return (
    <PageContainer>
      <PageHeader title="Audit" />
      <ErrorState title="Unable to load audit events" message={message} referenceId={referenceId} />
    </PageContainer>
  );
}
