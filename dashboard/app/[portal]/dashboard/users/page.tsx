import { UsersPageContent } from "@/features/users/users-page-content";

export const metadata = { title: "Users — JetPakistan Dashboard" };

export default function UsersDirectoryPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <UsersPageContent searchParams={searchParams} module="directory" />;
}
