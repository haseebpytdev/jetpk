import { SettingsErrorShell, SettingsModuleShell } from "@/features/settings/settings-module-shell";
import { SettingsLiveGate } from "@/features/settings/components/settings-live-gate";
import { parseSettingsQuery } from "@/lib/settings-query";
import { getSettingsModule, SettingsServiceError } from "@/services/settings-service";
import {
  ForbiddenState,
  SanitizedErrorState,
  ServiceUnavailableState,
  UnauthorizedState,
} from "@/components/ui/data-source-status";
import { ReadOnlyServiceError } from "@/lib/read-only/read-only-service";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import type { SettingsSection } from "@/types/access-control";

type Props = {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
  section: SettingsSection | "overview";
};

export async function SettingsPageContent({ searchParams, section }: Props) {
  const sp = await searchParams;
  const query = parseSettingsQuery({ ...sp, selectedSection: section });

  try {
    const result = await getSettingsModule(query);
    return (
      <SettingsLiveGate>
        <SettingsModuleShell section={section} result={result} />
      </SettingsLiveGate>
    );
  } catch (e) {
    return (
      <PageContainer>
        <PageHeader title="Settings" />
        <SettingsModuleError error={e} />
      </PageContainer>
    );
  }
}

function SettingsModuleError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="settings" />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  if (error instanceof SettingsServiceError) {
    return <SettingsErrorShell referenceId={error.referenceId} message={error.message} />;
  }
  throw error;
}
