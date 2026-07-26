import { Skeleton } from "@/components/ui/skeleton";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";

export default function ReportsLoading() {
  return (
    <PageContainer aria-busy="true" aria-label="Loading report route">
      <PageHeader title="Reports" description="Loading analytics foundation…" />
      <Skeleton className="h-24 w-full" />
      <Skeleton className="mt-4 h-40 w-full" />
    </PageContainer>
  );
}
