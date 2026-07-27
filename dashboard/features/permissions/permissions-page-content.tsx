import { PermissionsWorkspace } from "@/features/permissions/permissions-workspace";
import { UsersModuleShell, UsersErrorShell } from "@/features/users/users-module-shell";
import { parsePermissionsQuery } from "@/lib/permissions-query";
import { getPermissionsModule, PermissionsServiceError } from "@/services/permission-service";
import { Skeleton } from "@/components/ui/skeleton";

type Props = {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
};

export async function PermissionsPageContent({ searchParams }: Props) {
  const sp = await searchParams;
  const query = parsePermissionsQuery(sp);

  try {
    const result = await getPermissionsModule(query);

    if (result.state === "loading") {
      return (
        <UsersModuleShell module="permissions">
          <div aria-busy="true" aria-label="Loading permissions" data-testid="permissions-loading-state">
            <Skeleton className="h-24 w-full" />
            <Skeleton className="mt-4 h-40 w-full" />
          </div>
        </UsersModuleShell>
      );
    }

    return (
      <UsersModuleShell module="permissions">
        <PermissionsWorkspace result={result} />
      </UsersModuleShell>
    );
  } catch (e) {
    if (e instanceof PermissionsServiceError) {
      return <UsersErrorShell referenceId={e.referenceId} message={e.message} />;
    }
    throw e;
  }
}
