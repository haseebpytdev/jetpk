import type { ReactNode, HTMLAttributes, LabelHTMLAttributes } from "react";
import { cn } from "@/lib/utils";
import { LoadingState } from "@/components/ui/loading-state";
import { ErrorState } from "@/components/ui/error-state";
import { EmptyState } from "@/components/ui/empty-state";

export function PageContainer({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn("mx-auto w-full min-w-0 max-w-[1600px] space-y-6", className)} {...props} />;
}

export function PageHeader({
  title,
  description,
  breadcrumb,
  actions,
}: {
  title: string;
  description?: string;
  breadcrumb?: ReactNode;
  actions?: ReactNode;
}) {
  return (
    <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
      <div className="min-w-0">
        {breadcrumb}
        <h1 className="mt-2 font-display text-2xl font-bold tracking-tight sm:text-3xl">{title}</h1>
        {description ? <p className="mt-1 text-sm text-jp-muted">{description}</p> : null}
      </div>
      {actions ? <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div> : null}
    </div>
  );
}

export function SectionHeader({
  title,
  description,
  className,
}: {
  title: string;
  description?: string;
  className?: string;
}) {
  return (
    <div className={cn("mb-3", className)}>
      <h2 className="text-sm font-semibold text-gray-900">{title}</h2>
      {description ? <p className="mt-0.5 text-xs text-jp-muted">{description}</p> : null}
    </div>
  );
}

export function Breadcrumb({ items }: { items: { label: string; href?: string }[] }) {
  return (
    <nav aria-label="Breadcrumb" className="text-xs text-jp-muted">
      <ol className="flex flex-wrap gap-1">
        {items.map((item, i) => (
          <li key={item.label} className="flex items-center gap-1">
            {i > 0 ? <span aria-hidden>/</span> : null}
            <span className={i === items.length - 1 ? "text-gray-900" : undefined}>{item.label}</span>
          </li>
        ))}
      </ol>
    </nav>
  );
}

export function PreviewDataBanner({ className }: { className?: string }) {
  return (
    <div
      className={cn(
        "rounded-2xl border border-emerald-200 bg-emerald-50/60 px-4 py-3 text-sm text-emerald-900",
        className,
      )}
      role="status"
    >
      Preview data — synthetic records for layout and workflow testing. Not live production data.
    </div>
  );
}

export function Label({ className, ...props }: LabelHTMLAttributes<HTMLLabelElement>) {
  return <label className={cn("mb-1 block text-xs font-medium text-gray-700", className)} {...props} />;
}

export function VisuallyHidden({ children }: { children: ReactNode }) {
  return <span className="sr-only">{children}</span>;
}

/** Shared dashboard page shell contract — title, notices, filters, and state regions. */
export function DashboardPageShell({
  title,
  description,
  breadcrumb,
  actions,
  previewNotice,
  dataSourceNotice,
  summary,
  filters,
  activeFilters,
  children,
  state,
  error,
  empty,
  className,
}: {
  title: string;
  description?: string;
  breadcrumb?: ReactNode;
  actions?: ReactNode;
  previewNotice?: ReactNode;
  dataSourceNotice?: ReactNode;
  summary?: ReactNode;
  filters?: ReactNode;
  activeFilters?: ReactNode;
  children?: ReactNode;
  state?: "loading" | "ready" | "empty" | "error";
  error?: { title: string; message: string; referenceId: string; onRetry?: () => void };
  empty?: { title: string; description: string };
  className?: string;
}) {
  return (
    <PageContainer className={className}>
      <PageHeader title={title} description={description} breadcrumb={breadcrumb} actions={actions} />
      {previewNotice}
      {dataSourceNotice}
      {summary}
      {filters}
      {activeFilters}
      {state === "loading" ? <LoadingState /> : null}
      {state === "error" && error ? (
        <ErrorState
          title={error.title}
          message={error.message}
          referenceId={error.referenceId}
          onRetry={error.onRetry}
        />
      ) : null}
      {state === "empty" && empty ? <EmptyState title={empty.title} description={empty.description} /> : null}
      {state === "ready" || !state ? children : null}
    </PageContainer>
  );
}

/** @deprecated Use FixtureDataNotice from data-source-status for explicit source labeling. */
export { PreviewDataBanner as PreviewNotice };
