import { buildFixtureMetadata, buildLaravelMetadata, resolveDataSourceMode } from "@/lib/read-only/data-source";
import type {
  DataSourceMetadata,
  ReadOnlyResponseEnvelope,
  ReadOnlyWarning,
} from "@/types/read-only-integration";
import { READ_ONLY_SCHEMA_VERSION } from "@/types/read-only-integration";

export function createReadOnlyEnvelope<TData>(params: {
  data: TData;
  metadata?: Partial<DataSourceMetadata>;
  pagination?: ReadOnlyResponseEnvelope<TData>["pagination"];
  filters?: ReadOnlyResponseEnvelope<TData>["filters"];
  warnings?: ReadOnlyWarning[];
  referenceTime?: string | null;
}): ReadOnlyResponseEnvelope<TData> {
  const mode = resolveDataSourceMode();
  const generatedAt = new Date().toISOString();
  const meta =
    mode === "laravelReadOnly"
      ? buildLaravelMetadata(params.metadata)
      : buildFixtureMetadata(params.metadata);

  return {
    data: params.data,
    meta,
    pagination: params.pagination,
    filters: params.filters,
    source: meta.source,
    generatedAt,
    referenceTime: params.referenceTime ?? meta.referenceTime,
    warnings: params.warnings ?? [],
    schemaVersion: READ_ONLY_SCHEMA_VERSION,
  };
}

export function isReadOnlyEnvelope<T>(value: unknown): value is ReadOnlyResponseEnvelope<T> {
  if (!value || typeof value !== "object") {
    return false;
  }
  const record = value as Record<string, unknown>;
  return (
    "data" in record &&
    "meta" in record &&
    "source" in record &&
    "generatedAt" in record &&
    "schemaVersion" in record &&
    Array.isArray(record.warnings)
  );
}
