import { DashboardLink as Link } from "@/components/dashboard/dashboard-link";
import { Breadcrumb, PageContainer, PageHeader } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { Skeleton } from "@/components/ui/skeleton";
import { ErrorState } from "@/components/ui/error-state";
import { UsersWorkspace } from "@/features/users/users-workspace";
import { UserCreatePanel } from "@/features/users/components/user-create-panel";
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
  directoryScope?: "users" | "staff";
};

export function UsersModuleShell({ module, result, children, directoryScope = "users" }: Props) {
  const isStaff = directoryScope === "staff";
  const current = SUBROUTES.find((r) => r.key === module) ?? SUBROUTES[0];

  return (
    <PageContainer>
      <PreviewModeBadgeSlot />
      <PageHeader
        breadcrumb={
          <Breadcrumb
            items={[
              { label: "Home" },
              { label: "Administration" },
              { label: isStaff ? "Staff" : "Users" },
              ...(isStaff ? [] : [{ label: current.label }]),
            ]}
          />
        }
        title={isStaff ? "Staff" : "Users"}
        description={
          isStaff
            ? "Internal JetPakistan employees only. Agent and Agent Staff accounts are listed under Users."
            : "All platform accounts: Platform Admin, Staff, Customer, Agent, and Agent Staff."
        }
      />
      <DataSourceNoticeSlot />

      {isStaff ? null : (
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
      )}

      {module !== "directory" && children ? (
        children
      ) : module === "directory" && result?.state === "loading" ? (
        <UsersLoadingState label={isStaff ? "staff" : "users"} />
      ) : module === "directory" && result ? (
        <>
          <UserCreatePanel accountType={isStaff ? "staff" : "customer"} />
          <UsersWorkspace result={result} basePath={isStaff ? "/staff" : "/users"} />
        </>
      ) : null}
    </PageContainer>
  );
}

function UsersLoadingState({ label }: { label: string }) {
  return (
    <div aria-busy="true" aria-label={`Loading ${label}`} data-testid="users-loading-state">
      <Skeleton className="h-24 w-full" />
      <Skeleton className="mt-4 h-40 w-full" />
    </div>
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
