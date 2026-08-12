import { DashboardLink as Link } from "@/components/dashboard/dashboard-link";
import { Breadcrumb, PageContainer, PageHeader } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { Skeleton } from "@/components/ui/skeleton";
import { ErrorState } from "@/components/ui/error-state";
import { ReportsWorkspace } from "@/features/reports/reports-workspace";
import type { ReportModuleResult, ReportsModuleKey } from "@/types/report";

const SUBROUTES: { key: ReportsModuleKey; label: string; href: string }[] = [
  { key: "overview", label: "Overview", href: "/reports" },
  { key: "sales", label: "Sales", href: "/reports/sales" },
  { key: "bookings", label: "Bookings", href: "/reports/bookings" },
  { key: "payments", label: "Payments", href: "/reports/payments" },
  { key: "operations", label: "Operations", href: "/reports/operations" },
];

type Props = {
  module: ReportsModuleKey;
  result: ReportModuleResult;
};

export function ReportsModuleShell({ module, result }: Props) {
  const current = SUBROUTES.find((r) => r.key === module) ?? SUBROUTES[0];

  return (
    <PageContainer>
      <PreviewModeBadgeSlot />
      <PageHeader
        breadcrumb={
          <Breadcrumb items={[{ label: "Home" }, { label: "Insights & system" }, { label: "Reports" }, { label: current.label }]} />
        }
        title="Reports"
        description="Operational and commercial analytics with explicit currency handling and read-only CSV export."
      />
      <DataSourceNoticeSlot />

      <nav aria-label="Reports sections" className="flex flex-wrap gap-2">
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

      {result.state === "loading" ? (
        <div aria-busy="true" aria-label="Loading report data" data-testid="reports-loading-state">
          <Skeleton className="h-32 w-full" />
          <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {Array.from({ length: 4 }).map((_, i) => (
              <Skeleton key={i} className="h-28" />
            ))}
          </div>
        </div>
      ) : (
        <ReportsWorkspace result={result} />
      )}
    </PageContainer>
  );
}

export function ReportsErrorShell({
  referenceId,
  message,
}: {
  referenceId: string;
  message: string;
}) {
  return (
    <PageContainer>
      <PageHeader title="Reports" />
      <ErrorState title="Unable to load reports" message={message} referenceId={referenceId} />
    </PageContainer>
  );
}
