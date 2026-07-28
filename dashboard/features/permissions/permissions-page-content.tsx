import { PermissionsWorkspace } from "@/features/permissions/permissions-workspace";
import { UsersModuleShell, UsersErrorShell } from "@/features/users/users-module-shell";
import { parsePermissionsQuery } from "@/lib/permissions-query";
import { getPermissionsModule, PermissionsServiceError } from "@/services/permission-service";
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
    return (
      <PageContainer>
        <PageHeader title="Permissions" />
        <PermissionsModuleError error={e} />
      </PageContainer>
    );
  }
}

function PermissionsModuleError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="permissions" />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  if (error instanceof PermissionsServiceError) {
    return <UsersErrorShell referenceId={error.referenceId} message={error.message} />;
  }
  throw error;
}
