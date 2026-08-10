import { SettingsPageContent } from "@/features/settings/settings-page-content";

export const metadata = { title: "General Settings — JetPakistan Dashboard" };

export default function SettingsGeneralPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <SettingsPageContent searchParams={searchParams} section="general" />;
}
