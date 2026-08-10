import { SettingsPageContent } from "@/features/settings/settings-page-content";

export const metadata = { title: "Settings — JetPakistan Admin" };

export default function SettingsOverviewPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <SettingsPageContent searchParams={searchParams} section="overview" />;
}
