import { SuppliersWorkspace } from "@/features/suppliers/suppliers-workspace";
import { SuppliersErrorPanel } from "@/features/suppliers/suppliers-error-panel";
import { Breadcrumb, PageContainer, PageHeader, PreviewDataBanner } from "@/components/ui/page-layout";
import { Skeleton } from "@/components/ui/skeleton";
import { parseSuppliersQuery } from "@/lib/suppliers-query";
import { SuppliersServiceError, getSupplierDetail, getSuppliersPage } from "@/services/supplier-service";

type Props = {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
};

function SuppliersLoadingSkeleton() {
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

export async function SuppliersPageContent({ searchParams }: Props) {
  const sp = await searchParams;
  const query = parseSuppliersQuery(sp);

  if (query.previewLoading) {
    return (
      <PageContainer aria-busy="true" aria-label="Loading suppliers">
        <PageHeader
          breadcrumb={
            <Breadcrumb
              items={[{ label: "Home" }, { label: "Inventory & pricing" }, { label: "Suppliers" }]}
            />
          }
          title="Suppliers"
          description="Supplier inventory and integration status — mock preview data."
        />
        <SuppliersLoadingSkeleton />
      </PageContainer>
    );
  }

  try {
    const result = await getSuppliersPage(query);
    const selectedSupplier = query.selectedId ? await getSupplierDetail(query.selectedId) : null;

    return (
      <PageContainer>
        <PageHeader
          breadcrumb={
            <Breadcrumb
              items={[{ label: "Home" }, { label: "Inventory & pricing" }, { label: "Suppliers" }]}
            />
          }
          title="Suppliers"
          description="Supplier inventory and integration status — mock preview data with filters, sorting, and read-only detail."
        />
        <PreviewDataBanner />
        <SuppliersWorkspace query={query} result={result} selectedSupplier={selectedSupplier} />
      </PageContainer>
    );
  } catch (e) {
    if (e instanceof SuppliersServiceError) {
      return (
        <PageContainer>
          <PageHeader title="Suppliers" />
          <SuppliersErrorPanel referenceId={e.referenceId} message={e.message} />
        </PageContainer>
      );
    }
    throw e;
  }
}
