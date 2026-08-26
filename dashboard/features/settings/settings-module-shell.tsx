import { DashboardLink as Link } from "@/components/dashboard/dashboard-link";
import { Breadcrumb, PageContainer, PageHeader } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Skeleton } from "@/components/ui/skeleton";
import { GeneralSettingsWorkspace } from "@/features/settings/components/general-settings-workspace";
import { ApiConnectionsWorkspace } from "@/features/settings/components/api-connections-workspace";
import { NotificationSettingsWorkspace } from "@/features/settings/components/notification-settings-workspace";
import { SecuritySettingsWorkspace } from "@/features/settings/components/security-settings-workspace";
import { SettingsOverview } from "@/features/settings/components/settings-overview";
import type { SettingsSection } from "@/types/access-control";
import type { SettingsModuleResult } from "@/types/settings-module";

const SUBROUTES: { section: SettingsSection | "overview"; label: string; href: string }[] = [
  { section: "overview", label: "Overview", href: "/settings" },
  { section: "general", label: "General", href: "/settings/general" },
  { section: "security", label: "Security", href: "/settings/security" },
  { section: "notifications", label: "Notifications", href: "/settings/notifications" },
  { section: "integrations", label: "API & Modules", href: "/integrations" },
];

type Props = {
  section: SettingsSection | "overview";
  result?: SettingsModuleResult;
};

function SettingsLoadingState() {
  return (
    <div aria-busy="true" aria-label="Loading settings" data-testid="settings-loading-state">
      <Skeleton className="h-24 w-full" />
      <Skeleton className="mt-4 h-40 w-full" />
      <Skeleton className="mt-4 h-56 w-full" />
    </div>
  );
}

function SettingsSectionContent({ section, result }: { section: SettingsSection | "overview"; result: SettingsModuleResult }) {
  if (result.state === "empty") {
    return (
      <EmptyState
        title="No settings data"
        description="The settings service returned an empty payload."
      />
    );
  }

  switch (section) {
    case "overview":
      return <SettingsOverview result={result} />;
    case "general":
      return <GeneralSettingsWorkspace result={result} />;
    case "security":
      return <SecuritySettingsWorkspace result={result} />;
    case "notifications":
      return <NotificationSettingsWorkspace result={result} />;
    case "integrations":
      return <ApiConnectionsWorkspace />;
    default:
      return null;
  }
}

export function SettingsModuleShell({ section, result }: Props) {
  const current = SUBROUTES.find((route) => route.section === section) ?? SUBROUTES[0];

  return (
    <PageContainer>
      <PreviewModeBadgeSlot />
      <PageHeader
        breadcrumb={
          <Breadcrumb items={[{ label: "Home" }, { label: "Insights & system" }, { label: "Settings" }, { label: current.label }]} />
        }
        title="Settings"
        description="Organization, security, notifications, and API & Modules management. Secrets are never displayed after save."
      />
      <DataSourceNoticeSlot />

      <nav aria-label="Settings sections" className="flex flex-wrap gap-2">
        {SUBROUTES.map((route) => (
          <Link
            key={route.section}
            href={route.href}
            className="min-h-11 rounded-xl border border-jp-border bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent aria-[current=page]:border-jp-accent aria-[current=page]:bg-emerald-50"
            aria-current={route.section === section ? "page" : undefined}
          >
            {route.label}
          </Link>
        ))}
      </nav>

      {result?.state === "loading" ? (
        <SettingsLoadingState />
      ) : result?.state === "error" ? (
        <ErrorState
          title="Unable to load settings"
          message="The settings service returned an error state."
          referenceId="SET-PREVIEW-STATE-ERR"
        />
      ) : result ? (
        <SettingsSectionContent section={section} result={result} />
      ) : null}
    </PageContainer>
  );
}

export function SettingsErrorShell({ referenceId, message }: { referenceId: string; message: string }) {
  return (
    <PageContainer>
      <PageHeader title="Settings" />
      <ErrorState title="Unable to load settings" message={message} referenceId={referenceId} />
    </PageContainer>
  );
}
