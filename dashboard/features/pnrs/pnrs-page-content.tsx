import { PnrsWorkspace } from "@/features/pnrs/pnrs-workspace";
import { PnrsErrorPanel } from "@/features/pnrs/pnrs-error-panel";
import { Breadcrumb, PageContainer, PageHeader } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { Skeleton } from "@/components/ui/skeleton";
import { parsePnrsQuery } from "@/lib/pnrs-query";
import { PnrsServiceError, getPnrDetail, getPnrsPage } from "@/services/pnr-service";
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

function PnrsLoadingSkeleton() {
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

export async function PnrsPageContent({ searchParams }: Props) {
  const sp = await searchParams;
  const query = parsePnrsQuery(sp);

  if (query.previewLoading) {
    return (
      <PageContainer aria-busy="true" aria-label="Loading PNRs and orders">
        <PageHeader
          breadcrumb={
            <Breadcrumb items={[{ label: "Home" }, { label: "Operations" }, { label: "PNRs & Orders" }]} />
          }
          title="PNRs & Orders"
          description="GDS PNRs and supplier order references."
        />
        <PnrsLoadingSkeleton />
      </PageContainer>
    );
  }

  try {
    const result = await getPnrsPage(query);
    const selectedPnr = query.selectedId ? await getPnrDetail(query.selectedId) : null;

    return (
      <PageContainer>
        <PreviewModeBadgeSlot />
        <PageHeader
          breadcrumb={
            <Breadcrumb items={[{ label: "Home" }, { label: "Operations" }, { label: "PNRs & Orders" }]} />
          }
          title="PNRs & Orders"
          description="GDS PNRs, NDC orders, and supplier references with filters, sorting, and read-only detail."
        />
        <DataSourceNoticeSlot />
        <PnrsWorkspace query={query} result={result} selectedPnr={selectedPnr} />
      </PageContainer>
    );
  } catch (e) {
    return (
      <PageContainer>
        <PageHeader title="PNRs & Orders" />
        <PnrsModuleError error={e} />
      </PageContainer>
    );
  }
}

function PnrsModuleError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="PNRs and orders" />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  if (error instanceof PnrsServiceError) {
    return <PnrsErrorPanel referenceId={error.referenceId} message={error.message} />;
  }
  throw error;
}
