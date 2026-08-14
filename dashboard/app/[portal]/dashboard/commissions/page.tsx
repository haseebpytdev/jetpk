import { CommissionsWorkspace } from "@/features/commissions/commissions-workspace";
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
import { getCommissionsOverview } from "@/services/ops-modules-service";

export const metadata = { title: "Commissions — JetPakistan Dashboard" };

export default async function CommissionsPage() {
  try {
    const overview = await getCommissionsOverview();

    return (
      <PageContainer>
        <PreviewModeBadgeSlot />
        <PageHeader
          breadcrumb={<Breadcrumb items={[{ label: "Home" }, { label: "Finance" }, { label: "Commissions" }]} />}
          title="Commissions"
          description="Agent commission ledger and pending entry review through the authoritative Laravel commission service."
        />
        <DataSourceNoticeSlot />
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-testid="commissions-kpis">
          {[
            ["Pending", overview.kpis.pending],
            ["Approved unpaid", overview.kpis.approvedUnpaid],
            ["Paid this month", overview.kpis.paidThisMonth],
            ["Active agents", overview.kpis.activeAgents],
          ].map(([label, value]) => (
            <div key={String(label)} className="rounded-xl border border-jp-border bg-white p-4">
              <p className="text-xs text-jp-muted">{label}</p>
              <p className="mt-1 text-xl font-semibold tabular-nums">{value}</p>
            </div>
          ))}
        </div>
        <CommissionsWorkspace pendingEntries={overview.pendingEntries ?? []} />
        {overview.agents.length === 0 ? (
          <EmptyState title="No agents" description="No commission agents are available for this agency." />
        ) : (
          <ul className="mt-4 divide-y divide-jp-border rounded-xl border border-jp-border bg-white" data-testid="commissions-agents">
            {overview.agents.map((agent) => (
              <li key={agent.id} className="p-4 text-sm">
                <p className="font-medium text-gray-900">
                  {agent.name} <span className="text-jp-muted">({agent.code})</span>
                </p>
                <p className="text-jp-muted">Balance snapshot available from Laravel commission service.</p>
              </li>
            ))}
          </ul>
        )}
      </PageContainer>
    );
  } catch (error) {
    return (
      <PageContainer>
        <PageHeader title="Commissions" />
        <ModuleError error={error} />
      </PageContainer>
    );
  }
}

function ModuleError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="commissions" />;
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
