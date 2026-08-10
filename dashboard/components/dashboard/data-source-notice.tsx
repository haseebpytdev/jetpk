"use client";

import { Suspense } from "react";
import { useSearchParams } from "next/navigation";
import { getDashboardMode } from "@/lib/preview";
import { resolveDataSourceMode } from "@/lib/read-only/data-source";
import { FixtureDataNotice, LiveReadOnlyNotice, StaleDataNotice } from "@/components/ui/data-source-status";
import type { DataSourceMetadata } from "@/types/read-only-integration";
import { isStaleMetadata } from "@/lib/read-only/data-source";

/** Renders module-level source notice unless the shell preview gate is active. */
export function DataSourceNotice({ meta }: { meta?: DataSourceMetadata | null }) {
  const searchParams = useSearchParams();
  if (getDashboardMode() === "live" || searchParams.get("dataSourcePreview")) {
    return null;
  }

  const mode = resolveDataSourceMode();

  if (mode === "fixture") {
    return <FixtureDataNotice />;
  }

  if (mode === "laravelReadOnly") {
    return (
      <>
        <LiveReadOnlyNotice />
        {meta && isStaleMetadata(meta) ? <StaleDataNotice staleAfter={meta.staleAfter} className="mt-3" /> : null}
      </>
    );
  }

  return null;
}

/** Visible preview-mode label for regression tests and operators. */
export function PreviewModeBadge() {
  const searchParams = useSearchParams();
  if (searchParams.get("dataSourcePreview")) {
    return null;
  }

  const mode = resolveDataSourceMode();
  if (mode !== "fixture") {
    return null;
  }

  return (
    <p className="sr-only" data-testid="preview-mode-badge">
      Preview mode
    </p>
  );
}

export function DataSourceNoticeSlot() {
  return (
    <Suspense fallback={null}>
      <DataSourceNotice />
    </Suspense>
  );
}

export function PreviewModeBadgeSlot() {
  return (
    <Suspense fallback={null}>
      <PreviewModeBadge />
    </Suspense>
  );
}
