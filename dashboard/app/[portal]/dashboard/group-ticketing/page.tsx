import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { EmptyState } from "@/components/ui/empty-state";

export const metadata = { title: "Group ticketing — JetPakistan Dashboard" };

export default function GroupTicketingPage() {
  return (
    <PageContainer>
      <PageHeader title="Group ticketing" description="Group ticketing operator workspace." />
      <EmptyState
        title="Group ticketing"
        description="Next presentation shell for group ticketing. Domain logic remains Laravel."
      />
    </PageContainer>
  );
}
