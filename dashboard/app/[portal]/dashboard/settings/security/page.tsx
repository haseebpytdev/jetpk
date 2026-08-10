import { SettingsPageContent } from "@/features/settings/settings-page-content";

export const metadata = { title: "Security Settings — JetPakistan Dashboard" };

export default function SettingsSecurityPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <SettingsPageContent searchParams={searchParams} section="security" />;
}
