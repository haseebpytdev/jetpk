import { PageContainer, PageHeader, Breadcrumb } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { DashboardLink as Link } from "@/components/dashboard/dashboard-link";
import {
  ForbiddenState,
  SanitizedErrorState,
  ServiceUnavailableState,
  UnauthorizedState,
} from "@/components/ui/data-source-status";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { createReadOnlyService, ReadOnlyServiceError } from "@/lib/read-only/read-only-service";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";

type GoLiveItem = {
  key: string;
  label: string;
  ok: boolean;
  note?: string;
  href?: string | null;
  actionLabel?: string | null;
};

type GoLivePayload = {
  checklist: GoLiveItem[];
  managementMode?: string;
};

const goLiveService = createReadOnlyService<Record<string, never>, GoLivePayload>({
  module: "system-go-live",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(_q, options) {
      return createReadOnlyEnvelope({
        data: {
          managementMode: "validator_with_deep_links",
          checklist: [
            {
              key: "fixture",
              label: "Fixture go-live item",
              ok: true,
              note: "Preview only",
              href: "/settings/general",
              actionLabel: "Open settings",
            },
          ],
        },
        metadata: options?.metadata,
      });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(_q, options) {
      const envelope = await fetchDashboardApi<GoLivePayload>(DASHBOARD_API_ROUTES.systemGoLive, {
        signal: options?.signal,
      });
      return { ...envelope, data: envelope.data ?? { checklist: [] } };
    },
  },
});

export const metadata = { title: "Go-live checklist — JetPakistan Dashboard" };

export default async function GoLiveChecklistPage() {
  try {
    const health = (await goLiveService.fetchReadOnly({})).data;

    return (
      <PageContainer>
        <PreviewModeBadgeSlot />
        <PageHeader
          breadcrumb={<Breadcrumb items={[{ label: "Home" }, { label: "System" }, { label: "Go-live" }]} />}
          title="Go-live checklist"
          description="Live validators with links to the setting that must be fixed. Also covers the legacy deployment checklist route (/admin/deployment-checklist → this page). Refresh to re-run. Commercial UAT is never auto-completed."
        />
        <DataSourceNoticeSlot />
        <p className="mb-3 text-xs text-jp-muted" data-testid="deployment-checklist-mapping">
          Deployment checklist is mapped here (system go-live). System health remains at /system/health.
        </p>
        <ul className="divide-y divide-jp-border rounded-xl border border-jp-border bg-white" data-testid="go-live-checklist">
          {(health.checklist ?? []).map((item) => (
            <li key={item.key || item.label} className="flex flex-wrap items-center justify-between gap-3 p-4 text-sm">
              <div>
                <p>{item.label}</p>
                {item.note ? <p className="text-xs text-jp-muted">{item.note}</p> : null}
              </div>
              <div className="flex items-center gap-3">
                <span className={item.ok ? "text-emerald-700" : "text-amber-700"}>{item.ok ? "OK" : "Review"}</span>
                {item.href ? (
                  <Link href={item.href.replace("/admin/dashboard", "")} className="text-xs underline">
                    {item.actionLabel ?? "Open"}
                  </Link>
                ) : null}
              </div>
            </li>
          ))}
        </ul>
      </PageContainer>
    );
  } catch (error) {
    return (
      <PageContainer>
        <PageHeader title="Go-live checklist" />
        <GoLiveError error={error} />
      </PageContainer>
    );
  }
}

function GoLiveError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="go-live checklist" />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  throw error;
}
