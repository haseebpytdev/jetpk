import { CustomersWorkspace } from "@/features/customers/customers-workspace";
import { CustomersErrorPanel } from "@/features/customers/customers-error-panel";
import { Breadcrumb, PageContainer, PageHeader } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { Skeleton } from "@/components/ui/skeleton";
import { parseCustomersQuery } from "@/lib/customers-query";
import { CustomersServiceError, getCustomerDetail, getCustomersPage } from "@/services/customer-service";
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

function CustomersLoadingSkeleton() {
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

export async function CustomersPageContent({ searchParams }: Props) {
  const sp = await searchParams;
  const query = parseCustomersQuery(sp);

  if (query.previewLoading) {
    return (
      <PageContainer aria-busy="true" aria-label="Loading customers">
        <PageHeader
          breadcrumb={
            <Breadcrumb
              items={[{ label: "Home" }, { label: "Customers & partners" }, { label: "Customers" }]}
            />
          }
          title="Customers"
          description="Customer accounts and traveller profiles."
        />
        <CustomersLoadingSkeleton />
      </PageContainer>
    );
  }

  try {
    const result = await getCustomersPage(query);
    const selectedCustomer = query.selectedId ? await getCustomerDetail(query.selectedId) : null;

    return (
      <PageContainer>
        <PreviewModeBadgeSlot />
        <PageHeader
          breadcrumb={
            <Breadcrumb
              items={[{ label: "Home" }, { label: "Customers & partners" }, { label: "Customers" }]}
            />
          }
          title="Customers"
          description="Customer accounts and traveller profiles with filters, sorting, and read-only detail."
        />
        <DataSourceNoticeSlot />
        <CustomersWorkspace query={query} result={result} selectedCustomer={selectedCustomer} />
      </PageContainer>
    );
  } catch (e) {
    return (
      <PageContainer>
        <PageHeader title="Customers" />
        <CustomersModuleError error={e} />
      </PageContainer>
    );
  }
}

function CustomersModuleError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="customers" />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  if (error instanceof CustomersServiceError) {
    return <CustomersErrorPanel referenceId={error.referenceId} message={error.message} />;
  }
  throw error;
}
