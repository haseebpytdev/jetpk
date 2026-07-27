import Link from "next/link";
import { Breadcrumb, PageContainer, PageHeader, PreviewDataBanner } from "@/components/ui/page-layout";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Skeleton } from "@/components/ui/skeleton";
import { GeneralSettingsWorkspace } from "@/features/settings/components/general-settings-workspace";
import { IntegrationSettingsWorkspace } from "@/features/settings/components/integration-settings-workspace";
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
  { section: "integrations", label: "Integrations", href: "/settings/integrations" },
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
        title="No settings data in preview"
        description="The mock service returned an empty settings payload. Clear preview query flags or reload the page."
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
      return <IntegrationSettingsWorkspace result={result} />;
    default:
      return null;
  }
}

export function SettingsModuleShell({ section, result }: Props) {
  const current = SUBROUTES.find((route) => route.section === section) ?? SUBROUTES[0];

  return (
    <PageContainer>
      <PageHeader
        breadcrumb={
          <Breadcrumb items={[{ label: "Home" }, { label: "Insights & system" }, { label: "Settings" }, { label: current.label }]} />
        }
        title="Settings"
        description="System settings architecture — metadata and status only, no credentials."
      />
      <PreviewDataBanner />

      <div role="status" className="rounded-2xl border border-blue-200 bg-blue-50/60 px-4 py-3 text-sm text-blue-900">
        Dashboard preview only — settings are typed contracts with local component preview. No credentials, PCC, LNIATA,
        or publish workflow.
      </div>

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
          message="The settings preview service returned an error state."
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
