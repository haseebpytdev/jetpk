import { CustomersWorkspace } from "@/features/customers/customers-workspace";
import { CustomersErrorPanel } from "@/features/customers/customers-error-panel";
import { Breadcrumb, PageContainer, PageHeader, PreviewDataBanner } from "@/components/ui/page-layout";
import { Skeleton } from "@/components/ui/skeleton";
import { parseCustomersQuery } from "@/lib/customers-query";
import { CustomersServiceError, getCustomerDetail, getCustomersPage } from "@/services/customer-service";

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
          description="Customer accounts and traveller profiles — mock preview data."
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
        <PageHeader
          breadcrumb={
            <Breadcrumb
              items={[{ label: "Home" }, { label: "Customers & partners" }, { label: "Customers" }]}
            />
          }
          title="Customers"
          description="Customer accounts and traveller profiles — mock preview data with filters, sorting, and read-only detail."
        />
        <PreviewDataBanner />
        <CustomersWorkspace query={query} result={result} selectedCustomer={selectedCustomer} />
      </PageContainer>
    );
  } catch (e) {
    if (e instanceof CustomersServiceError) {
      return (
        <PageContainer>
          <PageHeader title="Customers" />
          <CustomersErrorPanel referenceId={e.referenceId} message={e.message} />
        </PageContainer>
      );
    }
    throw e;
  }
}
