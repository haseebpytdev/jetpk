import { AgentsWorkspace } from "@/features/agents/agents-workspace";
import { AgentsErrorPanel } from "@/features/agents/agents-error-panel";
import { Breadcrumb, PageContainer, PageHeader } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { Skeleton } from "@/components/ui/skeleton";
import { parseAgentsQuery } from "@/lib/agents-query";
import { AgentsServiceError, getAgentDetail, getAgentsPage } from "@/services/agent-service";
import {
  ForbiddenState,
  SanitizedErrorState,
  ServiceUnavailableState,
  UnauthorizedState,
} from "@/components/ui/data-source-status";
import { ReadOnlyServiceError } from "@/lib/read-only/read-only-service";

type Props = {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
};

function AgentsLoadingSkeleton() {
  return (
    <>
      <Skeleton className="mt-4 h-16 w-full" />
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        {Array.from({ length: 6 }).map((_, i) => (
          <Skeleton key={i} className="h-20" />
        ))}
      </div>
      <Skeleton className="h-56 w-full" />
      <Skeleton className="h-96 w-full" />
    </>
  );
}

export async function AgentsPageContent({ searchParams }: Props) {
  const sp = await searchParams;
  const query = parseAgentsQuery(sp);

  if (query.previewLoading) {
    return (
      <PageContainer aria-busy="true" aria-label="Loading agents">
        <PageHeader
          breadcrumb={
            <Breadcrumb
              items={[{ label: "Home" }, { label: "Customers & partners" }, { label: "Agents" }]}
            />
          }
          title="Agents"
          description="Agent and agency accounts."
        />
        <AgentsLoadingSkeleton />
      </PageContainer>
    );
  }

  try {
    const result = await getAgentsPage(query);
    const selectedAgent = query.selectedId ? await getAgentDetail(query.selectedId) : null;

    return (
      <PageContainer>
        <PreviewModeBadgeSlot />
        <PageHeader
          breadcrumb={
            <Breadcrumb
              items={[{ label: "Home" }, { label: "Customers & partners" }, { label: "Agents" }]}
            />
          }
          title="Agents"
          description="Agent and agency accounts with filters, sorting, and read-only detail."
        />
        <DataSourceNoticeSlot />
        <AgentsWorkspace query={query} result={result} selectedAgent={selectedAgent} />
      </PageContainer>
    );
  } catch (e) {
    return (
      <PageContainer>
        <PageHeader title="Agents" />
        <AgentsModuleError error={e} />
      </PageContainer>
    );
  }
}

function AgentsModuleError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="agents" />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  if (error instanceof AgentsServiceError) {
    return <AgentsErrorPanel referenceId={error.referenceId} message={error.message} />;
  }
  throw error;
}
