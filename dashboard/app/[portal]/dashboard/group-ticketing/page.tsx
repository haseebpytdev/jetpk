import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import { EmptyState } from "@/components/ui/empty-state";

export const metadata = { title: "Group ticketing — JetPakistan Dashboard" };

export default function GroupTicketingPage() {
  return (
    <PageContainer>
      <PageHeader title="Group ticketing" description="Intentionally deferred for JP-BO-04." />
      <EmptyState
        title="Group ticketing deferred"
        description="JetPakistan current ops do not require public umrah group catalog parity in JP-BO-04; separate phase. Group ticketing/group bookings are not PlatformModuleGate keys and are absent from production sidebar nav."
      />
      <p className="mt-3 text-xs text-jp-muted" data-testid="group-ticketing-deferred-reason">
        INTENTIONALLY_DEFERRED — JetPakistan current ops do not require public umrah group catalog parity in JP-BO-04;
        separate phase
      </p>
    </PageContainer>
  );
}
