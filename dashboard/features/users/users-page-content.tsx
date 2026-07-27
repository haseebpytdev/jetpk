import { UsersModuleShell, UsersErrorShell } from "@/features/users/users-module-shell";
import { parseUsersQuery } from "@/lib/users-query";
import { UsersServiceError, getUsersModule } from "@/services/user-service";
import type { UsersModuleKey } from "@/types/users";

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
    if (e instanceof UsersServiceError) {
      return <UsersErrorShell referenceId={e.referenceId} message={e.message} />;
    }
    throw e;
  }
}
