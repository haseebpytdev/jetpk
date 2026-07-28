import type { ReactNode } from "react";
import { cn } from "@/lib/utils";
import { mapModeToLabel } from "@/lib/read-only/data-source";
import type { DataSourceMetadata } from "@/types/read-only-integration";
import { Button } from "@/components/ui/button";
import { ErrorState } from "@/components/ui/error-state";
import { EmptyState } from "@/components/ui/empty-state";

function Notice({
  tone,
  title,
  children,
  testId,
  className,
}: {
  tone: "emerald" | "blue" | "amber" | "red" | "violet" | "gray";
  title: string;
  children: ReactNode;
  testId?: string;
  className?: string;
}) {
  const tones = {
    emerald: "border-emerald-200 bg-emerald-50/60 text-emerald-900",
    blue: "border-blue-200 bg-blue-50/60 text-blue-900",
    amber: "border-amber-200 bg-amber-50/70 text-amber-950",
    red: "border-red-200 bg-red-50/60 text-red-900",
    violet: "border-violet-200 bg-violet-50/60 text-violet-900",
    gray: "border-jp-border bg-gray-50 text-gray-800",
  };
  return (
    <div
      className={cn("rounded-2xl border px-4 py-3 text-sm", tones[tone], className)}
      role="status"
      data-testid={testId}
    >
      <p className="font-medium">{title}</p>
      <div className="mt-1 break-words text-[13px] leading-relaxed">{children}</div>
    </div>
  );
}

export function FixtureDataNotice({ className }: { className?: string }) {
  return (
    <Notice tone="emerald" title="Fixture preview data" testId="fixture-data-notice" className={className}>
      Synthetic records for layout and workflow testing. Not live production data.
    </Notice>
  );
}

export function LiveReadOnlyNotice({ className }: { className?: string }) {
  return (
    <Notice tone="blue" title="Laravel read-only" testId="live-readonly-notice" className={className}>
      Data is loaded from Laravel in read-only mode. Mutations are disabled in this phase.
    </Notice>
  );
}

export function AccessControlPreviewNotice({ className }: { className?: string }) {
  return (
    <Notice
      tone="blue"
      title="Access control preview"
      testId="access-control-preview-notice"
      className={className}
    >
      Dashboard preview only — access control contracts are fixture-backed and not connected to Laravel
      authentication.
    </Notice>
  );
}

export function StaleDataNotice({
  staleAfter,
  className,
}: {
  staleAfter: string | null;
  className?: string;
}) {
  return (
    <Notice tone="amber" title="Data may be stale" testId="stale-data-notice" className={className}>
      {staleAfter
        ? `Last refresh threshold passed at ${staleAfter}. Refresh to load current data.`
        : "Displayed data may no longer match the live system. Refresh to update."}
    </Notice>
  );
}

export function UnauthorizedState({ onSignIn }: { onSignIn?: () => void }) {
  return (
    <ErrorState
      title="Sign in required"
      message="Your session has expired or you are not signed in. Sign in through Laravel to continue."
      referenceId="AUTH-UNAUTHENTICATED"
      onRetry={onSignIn}
    />
  );
}

export function ForbiddenState({ resource }: { resource?: string }) {
  return (
    <ErrorState
      title="Access denied"
      message={
        resource
          ? `You do not have permission to view ${resource}. Contact an administrator if you need access.`
          : "You do not have permission to view this resource."
      }
      referenceId="AUTH-FORBIDDEN"
    />
  );
}

export function ServiceUnavailableState({ onRetry }: { onRetry?: () => void }) {
  return (
    <ErrorState
      title="Service unavailable"
      message="The data service is temporarily unavailable. Fixture data is not shown as a fallback."
      referenceId="SVC-UNAVAILABLE"
      onRetry={onRetry}
    />
  );
}

export function SanitizedErrorState({
  message,
  referenceId,
  onRetry,
}: {
  message: string;
  referenceId: string;
  onRetry?: () => void;
}) {
  return (
    <ErrorState title="Unable to load data" message={message} referenceId={referenceId} onRetry={onRetry} />
  );
}

export function DataSourceMetadataSummary({ meta }: { meta: DataSourceMetadata }) {
  return (
    <div
      className="flex flex-wrap gap-x-4 gap-y-1 rounded-xl border border-jp-border bg-white px-3 py-2 text-xs text-jp-muted"
      data-testid="data-source-metadata-summary"
    >
      <span>
        Source: <strong className="text-gray-900">{mapModeToLabel(meta.source)}</strong>
      </span>
      {meta.fetchedAt ? <span>Fetched: {meta.fetchedAt}</span> : null}
      {meta.referenceTime ? <span>Reference: {meta.referenceTime}</span> : null}
      {meta.recordCount !== null ? <span>Records: {meta.recordCount}</span> : null}
      {meta.fixtureRevision ? <span>Fixture: {meta.fixtureRevision}</span> : null}
      <span>Schema: {meta.schemaVersion}</span>
    </div>
  );
}

export function DataSourceEmptyState({
  title = "No records found",
  description = "Try adjusting filters or check back later.",
}: {
  title?: string;
  description?: string;
}) {
  return <EmptyState title={title} description={description} />;
}

export type DataSourcePreviewVariant =
  | "fixture"
  | "live"
  | "stale"
  | "unauthorized"
  | "forbidden"
  | "unavailable"
  | "error"
  | "metadata";

export function DataSourcePreviewStack({ variant }: { variant: DataSourcePreviewVariant }) {
  switch (variant) {
    case "fixture":
      return <FixtureDataNotice />;
    case "live":
      return <LiveReadOnlyNotice />;
    case "stale":
      return <StaleDataNotice staleAfter={new Date().toISOString()} />;
    case "unauthorized":
      return <UnauthorizedState />;
    case "forbidden":
      return <ForbiddenState resource="bookings" />;
    case "unavailable":
      return <ServiceUnavailableState />;
    case "error":
      return (
        <SanitizedErrorState
          message="Something went wrong while loading data."
          referenceId="PREVIEW-ERROR"
        />
      );
    case "metadata":
      return (
        <DataSourceMetadataSummary
          meta={{
            source: "fixture",
            fetchedAt: new Date().toISOString(),
            referenceTime: new Date().toISOString(),
            staleAfter: null,
            requestIdSafe: "PREVIEW-SAFE-001",
            recordCount: 42,
            fixtureRevision: "dash-10",
            schemaVersion: "dash-read-only-v1",
          }}
        />
      );
    default:
      return null;
  }
}

export function DataSourcePreviewActions({
  variant,
  onRetry,
}: {
  variant: DataSourcePreviewVariant;
  onRetry?: () => void;
}) {
  if (variant !== "unavailable" && variant !== "error") {
    return null;
  }
  return (
    <Button variant="secondary" size="sm" onClick={onRetry}>
      Retry
    </Button>
  );
}
