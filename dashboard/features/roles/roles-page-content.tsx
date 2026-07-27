import { RolesWorkspace } from "@/features/roles/roles-workspace";
import { UsersModuleShell } from "@/features/users/users-module-shell";
import { parseRolesQuery } from "@/lib/roles-query";
import { RolesServiceError, getRolesModule } from "@/services/role-service";
import { UsersErrorShell } from "@/features/users/users-module-shell";

type Props = {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
};

export async function RolesPageContent({ searchParams }: Props) {
  const sp = await searchParams;
  const query = parseRolesQuery(sp);

  try {
    const result = await getRolesModule(query);

    if (result.state === "loading") {
      return (
        <UsersModuleShell module="roles">
          <div aria-busy="true" aria-label="Loading roles" data-testid="roles-loading-state">
            <p className="text-sm text-jp-muted">Loading roles…</p>
          </div>
        </UsersModuleShell>
      );
    }

    return (
      <UsersModuleShell module="roles">
        <RolesWorkspace result={result} />
      </UsersModuleShell>
    );
  } catch (e) {
    if (e instanceof RolesServiceError) {
      return <UsersErrorShell referenceId={e.referenceId} message={e.message} />;
    }
    throw e;
  }
}
