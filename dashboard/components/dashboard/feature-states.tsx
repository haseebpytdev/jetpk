import type { ReactNode } from "react";
import { PageContainer, PageHeader } from "@/components/ui/page-layout";
import {
  DataSourceEmptyState,
  DataSourcePreviewStack,
  FixtureDataNotice,
  ForbiddenState,
  ServiceUnavailableState,
} from "@/components/ui/data-source-status";
import { ErrorState } from "@/components/ui/error-state";
import { LoadingState } from "@/components/ui/loading-state";

export function DashboardPageHeader({
  title,
  description,
  actions,
}: {
  title: string;
  description?: string;
  actions?: ReactNode;
}) {
  return <PageHeader title={title} description={description} actions={actions} />;
}

export function DashboardBreadcrumbs({ children }: { children: ReactNode }) {
  return (
    <nav aria-label="Breadcrumb" className="mb-4 text-sm text-jp-muted" data-testid="dashboard-breadcrumbs">
      {children}
    </nav>
  );
}

export function DashboardPreviewNotice({ children }: { children?: ReactNode }) {
  return (
    <div data-testid="dashboard-preview-notice">
      <FixtureDataNotice />
      {children}
    </div>
  );
}

export function DashboardUnavailableState({
  title = "Feature not operational",
  message = "This dashboard feature is not connected to a live operational endpoint yet.",
}: {
  title?: string;
  message?: string;
}) {
  return (
    <PageContainer>
      <ErrorState title={title} message={message} referenceId="DASH-UNAVAILABLE" />
    </PageContainer>
  );
}

export function DashboardAccessDenied({ resource }: { resource?: string }) {
  return (
    <div data-testid="dashboard-access-denied">
      <ForbiddenState resource={resource} />
    </div>
  );
}

export function DashboardLoadingState({ label = "Loading dashboard data" }: { label?: string }) {
  return (
    <div data-testid="dashboard-loading-state">
      <LoadingState label={label} />
    </div>
  );
}

export function DashboardEmptyState({
  title,
  description,
}: {
  title?: string;
  description?: string;
}) {
  return <DataSourceEmptyState title={title} description={description} />;
}

export function DashboardErrorState({ onRetry }: { onRetry?: () => void }) {
  return <ServiceUnavailableState onRetry={onRetry} />;
}

export function DashboardPreviewState({ variant }: { variant: Parameters<typeof DataSourcePreviewStack>[0]["variant"] }) {
  return <DataSourcePreviewStack variant={variant} />;
}
