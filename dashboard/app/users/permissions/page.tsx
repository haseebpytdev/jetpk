import { PermissionsPageContent } from "@/features/permissions/permissions-page-content";

export const metadata = { title: "Permissions — JetPakistan Admin Preview" };

export default function UsersPermissionsPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <PermissionsPageContent searchParams={searchParams} />;
}
