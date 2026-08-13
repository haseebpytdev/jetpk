import { SupportOperationalWorkspace } from "@/features/support/support-operational-workspace";
import { SupportPaginationNav } from "@/features/support/support-pagination";
import { Breadcrumb, PageContainer, PageHeader } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import {
  ForbiddenState,
  SanitizedErrorState,
  ServiceUnavailableState,
  UnauthorizedState,
} from "@/components/ui/data-source-status";
import { ReadOnlyServiceError } from "@/lib/read-only/read-only-service";
import {
  getSupportTicketsPage,
  SUPPORT_DEFAULT_PAGE_SIZE,
  SupportServiceError,
} from "@/services/support-service";

export const metadata = {
  title: "Support — JetPakistan Dashboard",
};

export default async function SupportPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  try {
    const params = await searchParams;
    const rawPage = Array.isArray(params.page) ? params.page[0] : params.page;
    const page = Math.max(1, Number.parseInt(rawPage ?? "1", 10) || 1);
    const result = await getSupportTicketsPage({ page, pageSize: SUPPORT_DEFAULT_PAGE_SIZE });

    return (
      <PageContainer>
        <PreviewModeBadgeSlot />
        <PageHeader
          breadcrumb={
            <Breadcrumb items={[{ label: "Home" }, { label: "Communications" }, { label: "Support" }]} />
          }
          title="Support tickets"
          description="Assign, reply, and resolve support cases from the Next operator shell. Default page size is 10."
        />
        <DataSourceNoticeSlot />
        <SupportPaginationNav
          page={result.pagination.page}
          pageCount={result.pagination.pageCount}
          total={result.pagination.total}
          pageSize={result.pagination.pageSize}
        />
        <SupportOperationalWorkspace tickets={result.tickets} />
        <SupportPaginationNav
          page={result.pagination.page}
          pageCount={result.pagination.pageCount}
          total={result.pagination.total}
          pageSize={result.pagination.pageSize}
        />
      </PageContainer>
    );
  } catch (error) {
    return (
      <PageContainer>
        <PageHeader title="Support tickets" />
        <SupportModuleError error={error} />
      </PageContainer>
    );
  }
}

function SupportModuleError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="support" />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  if (error instanceof SupportServiceError) {
    return <SanitizedErrorState message={error.message} referenceId={error.referenceId} />;
  }

  return (
    <SanitizedErrorState
      message="Support tickets could not be loaded."
      referenceId="SUP-LOAD"
    />
  );
}
