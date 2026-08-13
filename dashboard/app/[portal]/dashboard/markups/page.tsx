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
import { MarkupsWorkspace } from "@/features/markups/markups-workspace";
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
          description="Authoritative markup rules from the JetPakistan pricing domain. Create, edit, enable/disable, and archive using the existing MarkupRule engine."
        />
        <DataSourceNoticeSlot />
        {markups.length === 0 ? (
          <EmptyState title="No markup rules" description="No markup rules are configured yet. Create the first rule from the form." />
        ) : null}
        <MarkupsWorkspace markups={markups} />
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
