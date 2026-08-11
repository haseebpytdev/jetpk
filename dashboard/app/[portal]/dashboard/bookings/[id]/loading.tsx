import { PageContainer, PageHeader } from "@/components/ui/page-layout";

export default function BookingManagementLoading() {
  return (
    <PageContainer>
      <PageHeader title="Loading booking…" />
      <div className="h-64 animate-pulse rounded-2xl bg-gray-100" aria-hidden />
    </PageContainer>
  );
}
