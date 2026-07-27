import { SettingsErrorShell, SettingsModuleShell } from "@/features/settings/settings-module-shell";
import { parseSettingsQuery } from "@/lib/settings-query";
import { getSettingsModule, SettingsServiceError } from "@/services/settings-service";
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
    return <SettingsModuleShell section={section} result={result} />;
  } catch (e) {
    if (e instanceof SettingsServiceError) {
      return <SettingsErrorShell referenceId={e.referenceId} message={e.message} />;
    }
    throw e;
  }
}
