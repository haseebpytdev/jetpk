import Link from "next/link";
import { Breadcrumb, PageContainer, PageHeader, PreviewDataBanner } from "@/components/ui/page-layout";
import { Skeleton } from "@/components/ui/skeleton";
import { ErrorState } from "@/components/ui/error-state";
import { UsersWorkspace } from "@/features/users/users-workspace";
import type { UsersModuleKey, UsersModuleResult } from "@/types/users";

const SUBROUTES: { key: UsersModuleKey; label: string; href: string }[] = [
  { key: "directory", label: "Users", href: "/users" },
  { key: "roles", label: "Roles", href: "/users/roles" },
  { key: "permissions", label: "Permissions", href: "/users/permissions" },
];

type Props = {
  module: UsersModuleKey;
  result?: UsersModuleResult;
  children?: React.ReactNode;
};

export function UsersModuleShell({ module, result, children }: Props) {
  const current = SUBROUTES.find((r) => r.key === module) ?? SUBROUTES[0];

  return (
    <PageContainer>
      <PageHeader
        breadcrumb={
          <Breadcrumb items={[{ label: "Home" }, { label: "Insights & system" }, { label: "Users" }, { label: current.label }]} />
        }
        title="Users"
        description="Dashboard user directory, roles, and permissions — fixture-backed preview only."
      />
      <PreviewDataBanner />

      <div role="status" className="rounded-2xl border border-blue-200 bg-blue-50/60 px-4 py-3 text-sm text-blue-900">
        Dashboard preview only — access control contracts are fixture-backed and not connected to Laravel authentication.
      </div>

      <nav aria-label="Users sections" className="flex flex-wrap gap-2">
        {SUBROUTES.map((route) => (
          <Link
            key={route.key}
            href={route.href}
            className="min-h-11 rounded-xl border border-jp-border bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent aria-[current=page]:border-jp-accent aria-[current=page]:bg-emerald-50"
            aria-current={route.key === module ? "page" : undefined}
          >
            {route.label}
          </Link>
        ))}
      </nav>

      {module !== "directory" && children ? (
        children
      ) : module !== "directory" ? null : result?.state === "loading" ? (
        <div aria-busy="true" aria-label="Loading users" data-testid="users-loading-state">
          <Skeleton className="h-24 w-full" />
          <Skeleton className="mt-4 h-40 w-full" />
        </div>
      ) : result ? (
        <UsersWorkspace result={result} />
      ) : null}
    </PageContainer>
  );
}

export function UsersErrorShell({ referenceId, message }: { referenceId: string; message: string }) {
  return (
    <PageContainer>
      <PageHeader title="Users" />
      <ErrorState title="Unable to load users" message={message} referenceId={referenceId} />
    </PageContainer>
  );
}
