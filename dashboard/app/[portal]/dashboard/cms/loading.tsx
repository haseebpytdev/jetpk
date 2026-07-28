import { Skeleton } from "@/components/ui/skeleton";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";

export default function CmsLoading() {
  return (
    <PageContainer aria-busy="true" aria-label="Loading CMS">
      <PageHeader title="CMS" description="Loading content foundation…" />
      <Skeleton className="h-24 w-full" />
      <Skeleton className="mt-4 h-40 w-full" />
    </PageContainer>
  );
}
