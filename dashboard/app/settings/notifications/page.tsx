import { SettingsPageContent } from "@/features/settings/settings-page-content";

export const metadata = { title: "Notification Settings — JetPakistan Admin Preview" };

export default function SettingsNotificationsPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <SettingsPageContent searchParams={searchParams} section="notifications" />;
}
