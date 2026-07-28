import { RolesPageContent } from "@/features/roles/roles-page-content";

export const metadata = { title: "Roles — JetPakistan Admin Preview" };

export default function UsersRolesPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <RolesPageContent searchParams={searchParams} />;
}
