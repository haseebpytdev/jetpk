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
};

export async function UsersPageContent({ searchParams, module }: Props) {
  const sp = await searchParams;
  const query = parseUsersQuery(sp);

  if (module !== "directory") {
    return <UsersModuleShell module={module} />;
  }

  try {
    const result = await getUsersModule(query);
    return <UsersModuleShell module={module} result={result} />;
  } catch (e) {
    return (
      <PageContainer>
        <PageHeader title="Users" />
        <UsersModuleError error={e} />
      </PageContainer>
    );
  }
}

function UsersModuleError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="users" />;
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
