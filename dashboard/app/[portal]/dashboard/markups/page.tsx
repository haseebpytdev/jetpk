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
import { getMarkups } from "@/services/ops-modules-service";

export const metadata = { title: "Markups — JetPakistan Dashboard" };

export default async function MarkupsPage() {
  try {
    const markups = await getMarkups();

    return (
      <PageContainer>
        <PreviewModeBadgeSlot />
        <PageHeader
          breadcrumb={<Breadcrumb items={[{ label: "Home" }, { label: "Finance" }, { label: "Markups" }]} />}
          title="Markups"
          description="Live markup rules. Domain mutations remain on Laravel intake APIs."
        />
        <DataSourceNoticeSlot />
        {markups.length === 0 ? (
          <EmptyState title="No markup rules" description="No markup rules are configured for this agency." />
        ) : (
          <ul className="divide-y divide-jp-border rounded-xl border border-jp-border bg-white" data-testid="markups-list">
            {markups.map((row) => (
              <li key={row.id} className="p-4 text-sm">
                <p className="font-medium text-gray-900">{row.name}</p>
                <p className="text-jp-muted">
                  {row.ruleType} · {row.value} ({row.valueType}) · priority {row.priority} · {row.status}
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
        <PageHeader title="Markups" />
        <ModuleError error={error} resource="markups" />
      </PageContainer>
    );
  }
}

function ModuleError({ error, resource }: { error: unknown; resource: string }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource={resource} />;
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
