import { RolesWorkspace } from "@/features/roles/roles-workspace";
import { UsersModuleShell, UsersErrorShell } from "@/features/users/users-module-shell";
import { parseRolesQuery } from "@/lib/roles-query";
import { RolesServiceError, getRolesModule } from "@/services/role-service";
import { Skeleton } from "@/components/ui/skeleton";
import {
  ForbiddenState,
  SanitizedErrorState,
  ServiceUnavailableState,
  UnauthorizedState,
} from "@/components/ui/data-source-status";
import { ReadOnlyServiceError } from "@/lib/read-only/read-only-service";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";

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
            <Skeleton className="h-24 w-full" />
            <Skeleton className="mt-4 h-40 w-full" />
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
    return (
      <PageContainer>
        <PageHeader title="Roles" />
        <RolesModuleError error={e} />
      </PageContainer>
    );
  }
}

function RolesModuleError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="roles" />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  if (error instanceof RolesServiceError) {
    return <UsersErrorShell referenceId={error.referenceId} message={error.message} />;
  }
  throw error;
}
