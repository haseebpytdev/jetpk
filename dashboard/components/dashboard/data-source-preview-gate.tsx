"use client";

import { useSearchParams } from "next/navigation";
import {
  DataSourcePreviewStack,
  type DataSourcePreviewVariant,
} from "@/components/ui/data-source-status";

const VALID_VARIANTS = new Set<DataSourcePreviewVariant>([
  "fixture",
  "live",
  "stale",
  "unauthorized",
  "forbidden",
  "unavailable",
  "error",
  "metadata",
]);

function parseVariant(value: string | null): DataSourcePreviewVariant | null {
  if (!value || !VALID_VARIANTS.has(value as DataSourcePreviewVariant)) {
    return null;
  }
  return value as DataSourcePreviewVariant;
}

/** Dev/preview gate — append ?dataSourcePreview=fixture|live|stale|... to any /testdash route. */
export function DataSourcePreviewGate() {
  const searchParams = useSearchParams();
  const variant = parseVariant(searchParams.get("dataSourcePreview"));

  if (!variant) {
    return null;
  }

  return (
    <div className="mb-4 min-w-0" data-testid="data-source-preview-gate">
      <DataSourcePreviewStack variant={variant} />
    </div>
  );
}
