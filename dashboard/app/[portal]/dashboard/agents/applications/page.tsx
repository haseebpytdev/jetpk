import { EmptyState } from "@/components/ui/empty-state";
import { PageContainer, PageHeader, Breadcrumb } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import {
  ForbiddenState,
  SanitizedErrorState,
  ServiceUnavailableState,
  UnauthorizedState,
} from "@/components/ui/data-source-status";
import { ReadOnlyServiceError } from "@/lib/read-only/read-only-service";
import { getAgentApplications } from "@/services/ops-modules-service";

export const metadata = { title: "Agent applications — JetPakistan Dashboard" };

export default async function AgentApplicationsPage() {
  try {
    const applications = await getAgentApplications();

    return (
      <PageContainer>
        <PreviewModeBadgeSlot />
        <PageHeader
          breadcrumb={
            <Breadcrumb items={[{ label: "Home" }, { label: "Customers" }, { label: "Agent applications" }]} />
          }
          title="Agent applications"
          description="Review queue for agent onboarding. Approve/reject mutations remain Laravel intake."
        />
        <DataSourceNoticeSlot />
        {applications.length === 0 ? (
          <EmptyState title="No applications" description="No agent applications are waiting for review." />
        ) : (
          <ul
            className="divide-y divide-jp-border rounded-xl border border-jp-border bg-white"
            data-testid="agent-applications-list"
          >
            {applications.map((row) => (
              <li key={row.id} className="p-4 text-sm">
                <p className="font-medium text-gray-900">{row.agencyName}</p>
                <p className="text-jp-muted">
                  {row.contactName} · {row.contactEmail} · {row.status}
                </p>
              </li>
            ))}
          </ul>
        )}
      </PageContainer>
    );
  } catch (error) {
    return (
      <PageContainer>
        <PageHeader title="Agent applications" />
        <ModuleError error={error} />
      </PageContainer>
    );
  }
}

function ModuleError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="agent applications" />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  throw error;
}
