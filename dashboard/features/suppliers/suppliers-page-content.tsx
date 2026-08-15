import { Breadcrumb, PageContainer, PageHeader } from "@/components/ui/page-layout";
import { SuppliersWorkspace } from "@/features/suppliers/suppliers-workspace";
import { SuppliersErrorPanel } from "@/features/suppliers/suppliers-error-panel";
import { DashboardLink as Link } from "@/components/dashboard/dashboard-link";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { Skeleton } from "@/components/ui/skeleton";
import { parseSuppliersQuery } from "@/lib/suppliers-query";
import { SuppliersServiceError, getSupplierDetail, getSuppliersPage } from "@/services/supplier-service";
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
          description="Supplier inventory and integration status."
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
        <PreviewModeBadgeSlot />
        <PageHeader
          breadcrumb={
            <Breadcrumb
              items={[{ label: "Home" }, { label: "Inventory & pricing" }, { label: "Suppliers" }]}
            />
          }
          title="Suppliers"
          description="Business vendor records grouped from connected providers. Configure technical channels on API Connections."
        />
        <DataSourceNoticeSlot />
        <p className="text-sm">
          <Link className="font-medium text-jp-accent-muted hover:underline" href="/api-connections">
            View / configure API connections
          </Link>
        </p>
        <SuppliersWorkspace query={query} result={result} selectedSupplier={selectedSupplier} />
      </PageContainer>
    );
  } catch (e) {
    return (
      <PageContainer>
        <PageHeader title="Suppliers" />
        <SuppliersModuleError error={e} />
      </PageContainer>
    );
  }
}

function SuppliersModuleError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="suppliers" />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  if (error instanceof SuppliersServiceError) {
    return <SuppliersErrorPanel referenceId={error.referenceId} message={error.message} />;
  }
  throw error;
}
