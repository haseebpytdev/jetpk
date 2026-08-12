import { UsersPageContent } from "@/features/users/users-page-content";

export const metadata = { title: "Staff — JetPakistan Dashboard" };

export default function StaffDirectoryPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <UsersPageContent searchParams={searchParams} module="directory" directoryScope="staff" />;
}
