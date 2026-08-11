import { PageContainer, PageHeader, Breadcrumb } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
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

type SystemHealthPayload = {
  checklist: Array<{ label: string; ok: boolean }>;
};

const healthService = createReadOnlyService<Record<string, never>, SystemHealthPayload>({
  module: "system-go-live",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(_q, options) {
      return createReadOnlyEnvelope({
        data: { checklist: [{ label: "Fixture go-live item", ok: true }] },
        metadata: options?.metadata,
      });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(_q, options) {
      const envelope = await fetchDashboardApi<SystemHealthPayload>(DASHBOARD_API_ROUTES.systemHealth, {
        signal: options?.signal,
      });
      return { ...envelope, data: { checklist: envelope.data?.checklist ?? [] } };
    },
  },
});

export const metadata = { title: "Go-live checklist — JetPakistan Dashboard" };

export default async function GoLiveChecklistPage() {
  try {
    const health = (await healthService.fetchReadOnly({})).data;

    return (
      <PageContainer>
        <PreviewModeBadgeSlot />
        <PageHeader
          breadcrumb={<Breadcrumb items={[{ label: "Home" }, { label: "System" }, { label: "Go-live" }]} />}
          title="Go-live checklist"
          description="Production readiness checklist backed by Laravel system safety configuration checks."
        />
        <DataSourceNoticeSlot />
        <ul className="divide-y divide-jp-border rounded-xl border border-jp-border bg-white" data-testid="go-live-checklist">
          {health.checklist.map((item) => (
            <li key={item.label} className="flex items-center justify-between gap-3 p-4 text-sm">
              <span>{item.label}</span>
              <span className={item.ok ? "text-emerald-700" : "text-amber-700"}>{item.ok ? "OK" : "Review"}</span>
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
