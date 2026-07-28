/** JETPK-DASH-11 — read-only Laravel integration contracts (architecture only in Prompt 01). */

export const DATA_SOURCE_MODES = ["fixture", "laravelReadOnly", "unavailable"] as const;
export type DataSourceMode = (typeof DATA_SOURCE_MODES)[number];

export const DATA_SOURCE_STATES = [
  "loading",
  "ready",
  "stale",
  "empty",
  "unauthorized",
  "forbidden",
  "unavailable",
  "error",
] as const;
export type DataSourceState = (typeof DATA_SOURCE_STATES)[number];

export type DataSourceMetadata = {
  source: DataSourceMode;
  fetchedAt: string | null;
  referenceTime: string | null;
  staleAfter: string | null;
  /** Safe, non-sensitive correlation id for support — never raw server request ids. */
  requestIdSafe: string | null;
  recordCount: number | null;
  fixtureRevision: string | null;
  schemaVersion: string;
};

export type ReadOnlyPagination = {
  page: number;
  pageSize: number;
  total: number;
  pageCount: number;
};

export type ReadOnlyWarning = {
  code: string;
  message: string;
};

export type ReadOnlyResponseEnvelope<TData> = {
  data: TData;
  meta: DataSourceMetadata;
  pagination?: ReadOnlyPagination;
  filters?: Record<string, string | string[] | number | boolean | null>;
  source: DataSourceMode;
  generatedAt: string;
  referenceTime: string | null;
  warnings: ReadOnlyWarning[];
  schemaVersion: string;
};

export const READ_ONLY_ERROR_CODES = [
  "unauthenticated",
  "forbidden",
  "validation_error",
  "not_found",
  "unavailable",
  "timeout",
  "rate_limited",
  "internal_error",
] as const;
export type ReadOnlyErrorCode = (typeof READ_ONLY_ERROR_CODES)[number];

export type ReadOnlyErrorEnvelope = {
  error: {
    code: ReadOnlyErrorCode;
    message: string;
    referenceIdSafe: string;
    details?: Record<string, string[]>;
  };
  meta: Pick<DataSourceMetadata, "source" | "schemaVersion">;
};

export type ReadOnlyModuleKey =
  | "session"
  | "overview"
  | "bookings"
  | "payments"
  | "customers"
  | "suppliers"
  | "agents"
  | "pnrs"
  | "tickets"
  | "reports"
  | "cms"
  | "users"
  | "roles"
  | "permissions"
  | "settings"
  | "audit";

export type ReadOnlyEndpointContract = {
  module: ReadOnlyModuleKey;
  routeConcept: string;
  method: "GET";
  queryParameters: string[];
  pagination: boolean;
  filtering: boolean;
  sorting: boolean;
  authorization: string;
  sensitiveFieldExclusions: string[];
  cacheStaleBehavior: string;
  fixtureEquivalent: string;
  implementationStatus: "fixture" | "architecture" | "future_laravel";
};

export const READ_ONLY_SCHEMA_VERSION = "dash-read-only-v1";
