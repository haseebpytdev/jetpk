import { TicketsWorkspace } from "@/features/tickets/tickets-workspace";
import { TicketsErrorPanel } from "@/features/tickets/tickets-error-panel";
import { Breadcrumb, PageContainer, PageHeader } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { Skeleton } from "@/components/ui/skeleton";
import { parseTicketsQuery } from "@/lib/tickets-query";
import { getTicketDetail, getTicketsPage, TicketsServiceError } from "@/services/ticket-service";
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

function TicketsLoadingSkeleton() {
  return (
    <>
      <Skeleton className="mt-4 h-16 w-full" />
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-7">
        {Array.from({ length: 7 }).map((_, i) => (
          <Skeleton key={i} className="h-20" />
        ))}
      </div>
      <Skeleton className="h-56 w-full" />
      <Skeleton className="h-96 w-full" />
    </>
  );
}

export async function TicketsPageContent({ searchParams }: Props) {
  const sp = await searchParams;
  const query = parseTicketsQuery(sp);

  if (query.previewLoading) {
    return (
      <PageContainer aria-busy="true" aria-label="Loading tickets">
        <PageHeader
          breadcrumb={
            <Breadcrumb
              items={[{ label: "Home" }, { label: "Operations" }, { label: "Tickets & Documents" }]}
            />
          }
          title="Tickets & Documents"
          description="Ticket and fulfilment document records."
        />
        <TicketsLoadingSkeleton />
      </PageContainer>
    );
  }

  try {
    const result = await getTicketsPage(query);
    const selectedTicket = query.selectedId ? await getTicketDetail(query.selectedId) : null;

    return (
      <PageContainer>
        <PreviewModeBadgeSlot />
        <PageHeader
          breadcrumb={
            <Breadcrumb
              items={[{ label: "Home" }, { label: "Operations" }, { label: "Tickets & Documents" }]}
            />
          }
          title="Tickets & Documents"
          description="Ticket and fulfilment document records with filters, sorting, and read-only detail."
        />
        <DataSourceNoticeSlot />
        <TicketsWorkspace query={query} result={result} selectedTicket={selectedTicket} />
      </PageContainer>
    );
  } catch (e) {
    return (
      <PageContainer>
        <PageHeader title="Tickets & Documents" />
        <TicketsModuleError error={e} />
      </PageContainer>
    );
  }
}

function TicketsModuleError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="tickets" />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  if (error instanceof TicketsServiceError) {
    return <TicketsErrorPanel referenceId={error.referenceId} message={error.message} />;
  }
  throw error;
}
