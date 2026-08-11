import { PageContainer, PageHeader, Breadcrumb } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import {
  ForbiddenState,
  SanitizedErrorState,
  ServiceUnavailableState,
  UnauthorizedState,
} from "@/components/ui/data-source-status";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { createReadOnlyService, ReadOnlyServiceError, type ReadOnlyFetchOptions } from "@/lib/read-only/read-only-service";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";

type SystemHealthPayload = {
  checks: Record<string, string | number | boolean>;
  checklist: Array<{ label: string; ok: boolean }>;
};

const healthService = createReadOnlyService<Record<string, never>, SystemHealthPayload>({
  module: "system-health",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(_q, options) {
      return createReadOnlyEnvelope({
        data: {
          checks: { appEnv: "fixture", dbConnectionOk: true },
          checklist: [{ label: "Fixture checklist", ok: true }],
        },
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
      return {
        ...envelope,
        data: {
          checks: envelope.data?.checks ?? {},
          checklist: envelope.data?.checklist ?? [],
        },
      };
    },
  },
});

async function getSystemHealth(options?: ReadOnlyFetchOptions): Promise<SystemHealthPayload> {
  return (await healthService.fetchReadOnly({}, options)).data;
}

export const metadata = { title: "System health — JetPakistan Dashboard" };

export default async function SystemHealthPage() {
  try {
    const health = await getSystemHealth();

    return (
      <PageContainer>
        <PreviewModeBadgeSlot />
        <PageHeader
          breadcrumb={<Breadcrumb items={[{ label: "Home" }, { label: "System" }, { label: "Health" }]} />}
          title="System health"
          description="Sanitized operational health signals from Laravel system safety services."
        />
        <DataSourceNoticeSlot />
        <div className="grid gap-3 sm:grid-cols-2" data-testid="system-health-checks">
          {Object.entries(health.checks).map(([key, value]) => (
            <div key={key} className="rounded-xl border border-jp-border bg-white p-4 text-sm">
              <p className="text-xs text-jp-muted">{key}</p>
              <p className="mt-1 font-medium tabular-nums text-gray-900">{String(value)}</p>
            </div>
          ))}
        </div>
        <ul className="mt-4 divide-y divide-jp-border rounded-xl border border-jp-border bg-white" data-testid="system-health-checklist">
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
        <PageHeader title="System health" />
        <HealthError error={error} />
      </PageContainer>
    );
  }
}

function HealthError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="system health" />;
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
