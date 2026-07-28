import { SettingsPageContent } from "@/features/settings/settings-page-content";

export const metadata = { title: "Integration Settings — JetPakistan Admin Preview" };

export default function SettingsIntegrationsPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <SettingsPageContent searchParams={searchParams} section="integrations" />;
}
