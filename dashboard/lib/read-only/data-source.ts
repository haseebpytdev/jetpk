import { getDashboardMode } from "@/lib/preview";
import type { DataSourceMetadata, DataSourceMode, DataSourceState } from "@/types/read-only-integration";
import { READ_ONLY_SCHEMA_VERSION } from "@/types/read-only-integration";

function isMockDataEnabled(): boolean {
  if (process.env.NEXT_PUBLIC_USE_MOCK_DATA === "false") {
    return false;
  }
  return getDashboardMode() === "preview" || process.env.NEXT_PUBLIC_USE_MOCK_DATA === "true";
}

export function resolveDataSourceMode(): DataSourceMode {
  if (!isMockDataEnabled()) {
    return "laravelReadOnly";
  }
  if (process.env.NEXT_PUBLIC_DATA_SOURCE_UNAVAILABLE === "true") {
    return "unavailable";
  }
  return "fixture";
}

export function isValidDataSourceMode(value: string): value is DataSourceMode {
  return value === "fixture" || value === "laravelReadOnly" || value === "unavailable";
}

export function isValidDataSourceState(value: string): value is DataSourceState {
  return (
    value === "loading" ||
    value === "ready" ||
    value === "stale" ||
    value === "empty" ||
    value === "unauthorized" ||
    value === "forbidden" ||
    value === "unavailable" ||
    value === "error"
  );
}

export function buildFixtureMetadata(overrides?: Partial<DataSourceMetadata>): DataSourceMetadata {
  const now = new Date().toISOString();
  return {
    source: "fixture",
    fetchedAt: now,
    referenceTime: now,
    staleAfter: null,
    requestIdSafe: null,
    recordCount: overrides?.recordCount ?? null,
    fixtureRevision: process.env.NEXT_PUBLIC_FIXTURE_REVISION ?? "dash-10",
    schemaVersion: READ_ONLY_SCHEMA_VERSION,
    ...overrides,
  };
}

export function buildLaravelMetadata(overrides?: Partial<DataSourceMetadata>): DataSourceMetadata {
  const now = new Date().toISOString();
  return {
    source: "laravelReadOnly",
    fetchedAt: now,
    referenceTime: now,
    staleAfter: overrides?.staleAfter ?? null,
    requestIdSafe: overrides?.requestIdSafe ?? null,
    recordCount: overrides?.recordCount ?? null,
    fixtureRevision: null,
    schemaVersion: READ_ONLY_SCHEMA_VERSION,
    ...overrides,
  };
}

export function isStaleMetadata(meta: DataSourceMetadata, now = Date.now()): boolean {
  if (!meta.staleAfter) {
    return false;
  }
  const staleAt = Date.parse(meta.staleAfter);
  return Number.isFinite(staleAt) && now >= staleAt;
}

export function mapModeToLabel(mode: DataSourceMode): string {
  switch (mode) {
    case "fixture":
      return "Fixture preview";
    case "laravelReadOnly":
      return "Laravel read-only";
    case "unavailable":
      return "Unavailable";
    default:
      return mode;
  }
}
