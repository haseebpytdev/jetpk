import { SupportOperationalWorkspace } from "@/features/support/support-operational-workspace";
import { Breadcrumb, PageContainer, PageHeader } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import {
  ForbiddenState,
  SanitizedErrorState,
  ServiceUnavailableState,
  UnauthorizedState,
} from "@/components/ui/data-source-status";
import { ReadOnlyServiceError } from "@/lib/read-only/read-only-service";
import { getSupportTickets, SupportServiceError } from "@/services/support-service";

export const metadata = {
  title: "Support — JetPakistan Dashboard",
};

export default async function SupportPage() {
  try {
    const tickets = await getSupportTickets();

    return (
      <PageContainer>
        <PreviewModeBadgeSlot />
        <PageHeader
          breadcrumb={
            <Breadcrumb items={[{ label: "Home" }, { label: "Communications" }, { label: "Support" }]} />
          }
          title="Support tickets"
          description="Assign, reply, and resolve support cases from the Next operator shell."
        />
        <DataSourceNoticeSlot />
        <SupportOperationalWorkspace tickets={tickets} />
      </PageContainer>
    );
  } catch (error) {
    return (
      <PageContainer>
        <PageHeader title="Support tickets" />
        <SupportModuleError error={error} />
      </PageContainer>
    );
  }
}

function SupportModuleError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="support" />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  if (error instanceof SupportServiceError) {
    return <SanitizedErrorState message={error.message} referenceId={error.referenceId} />;
  }
  throw error;
}
