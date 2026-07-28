import { Skeleton } from "@/components/ui/skeleton";
import { PageContainer } from "@/components/ui/page-layout";

export default function CustomersLoading() {
  return (
    <PageContainer aria-busy="true" aria-label="Loading customers">
      <Skeleton className="h-10 w-64" />
      <Skeleton className="h-4 w-96 max-w-full" />
      <Skeleton className="mt-4 h-16 w-full" />
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        {Array.from({ length: 6 }).map((_, i) => (
          <Skeleton key={i} className="h-20" />
        ))}
      </div>
      <Skeleton className="h-56 w-full" />
      <Skeleton className="h-96 w-full" />
    </PageContainer>
  );
}
