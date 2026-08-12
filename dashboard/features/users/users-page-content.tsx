import { UsersModuleShell, UsersErrorShell } from "@/features/users/users-module-shell";
import { parseUsersQuery } from "@/lib/users-query";
import { UsersServiceError, getUsersModule } from "@/services/user-service";
import {
  ForbiddenState,
  SanitizedErrorState,
  ServiceUnavailableState,
  UnauthorizedState,
} from "@/components/ui/data-source-status";
import { ReadOnlyServiceError } from "@/lib/read-only/read-only-service";
import type { UsersModuleKey } from "@/types/users";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";

type Props = {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
  module: UsersModuleKey;
  directoryScope?: "users" | "staff";
};

export async function UsersPageContent({
  searchParams,
  module,
  directoryScope = "users",
}: Props) {
  const sp = await searchParams;
  const query = parseUsersQuery(sp, { directoryScope });
  query.directoryScope = directoryScope;

  if (module !== "directory") {
    return <UsersModuleShell module={module} />;
  }

  try {
    const result = await getUsersModule(query);
    return <UsersModuleShell module={module} result={result} directoryScope={directoryScope} />;
  } catch (e) {
    return (
      <PageContainer>
        <PageHeader title={directoryScope === "staff" ? "Staff" : "Users"} />
        <UsersModuleError error={e} resource={directoryScope === "staff" ? "staff" : "users"} />
      </PageContainer>
    );
  }
}

function UsersModuleError({ error, resource }: { error: unknown; resource: string }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource={resource} />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  if (error instanceof UsersServiceError) {
    return <UsersErrorShell referenceId={error.referenceId} message={error.message} />;
  }
  throw error;
}
