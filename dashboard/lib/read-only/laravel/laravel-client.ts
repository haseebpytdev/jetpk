import { createReadOnlyErrorEnvelope, mapHttpStatusToErrorCode, sanitizeErrorMessage } from "@/lib/read-only/error-envelope";
import { dashboardApiUrl } from "@/lib/read-only/laravel/api-base";
import type {
  DataSourceMetadata,
  ReadOnlyErrorEnvelope,
  ReadOnlyResponseEnvelope,
} from "@/types/read-only-integration";
import { READ_ONLY_SCHEMA_VERSION } from "@/types/read-only-integration";

export type LaravelFetchOptions = {
  signal?: AbortSignal;
  query?: Record<string, string | number | boolean | null | undefined>;
};

function serializeQuery(query?: LaravelFetchOptions["query"]): string {
  if (!query) return "";
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) {
    if (value === null || value === undefined || value === "") continue;
    params.set(key, String(value));
  }
  const serialized = params.toString();
  return serialized ? `?${serialized}` : "";
}

function normalizeLaravelEnvelope<T>(payload: Record<string, unknown>): ReadOnlyResponseEnvelope<T> {
  const meta = (payload.meta ?? {}) as DataSourceMetadata;
  return {
    data: payload.data as T,
    meta: {
      source: "laravelReadOnly",
      fetchedAt: (meta.fetchedAt as string | null) ?? (payload.generatedAt as string | null) ?? null,
      referenceTime: (meta.referenceTime as string | null) ?? (payload.referenceTime as string | null) ?? null,
      staleAfter: (meta.staleAfter as string | null) ?? null,
      requestIdSafe: (meta.requestIdSafe as string | null) ?? null,
      recordCount: (meta.recordCount as number | null) ?? null,
      fixtureRevision: null,
      schemaVersion: (meta.schemaVersion as string) ?? READ_ONLY_SCHEMA_VERSION,
    },
    pagination: payload.pagination as ReadOnlyResponseEnvelope<T>["pagination"],
    filters: payload.filters as ReadOnlyResponseEnvelope<T>["filters"],
    source: "laravelReadOnly",
    generatedAt: (payload.generatedAt as string) ?? new Date().toISOString(),
    referenceTime: (payload.referenceTime as string | null) ?? null,
    warnings: Array.isArray(payload.warnings) ? (payload.warnings as ReadOnlyResponseEnvelope<T>["warnings"]) : [],
    schemaVersion: (payload.schemaVersion as string) ?? READ_ONLY_SCHEMA_VERSION,
  };
}

function parseLaravelError(body: Record<string, unknown>, status: number): ReadOnlyErrorEnvelope {
  const errorBlock = body.error as Record<string, unknown> | undefined;
  const code = mapHttpStatusToErrorCode(status);
  const message =
    typeof errorBlock?.message === "string"
      ? sanitizeErrorMessage(errorBlock.message)
      : typeof body.message === "string"
        ? sanitizeErrorMessage(body.message)
        : undefined;

  return createReadOnlyErrorEnvelope({
    code: typeof errorBlock?.code === "string" ? (errorBlock.code as ReadOnlyErrorEnvelope["error"]["code"]) : code,
    referenceIdSafe:
      typeof errorBlock?.referenceIdSafe === "string"
        ? errorBlock.referenceIdSafe
        : `HTTP-${status}`,
    message,
  });
}

export async function fetchDashboardApi<T>(
  path: string,
  options?: LaravelFetchOptions,
): Promise<ReadOnlyResponseEnvelope<T>> {
  const url = `${dashboardApiUrl(path)}${serializeQuery(options?.query)}`;
  const response = await fetch(url, {
    method: "GET",
    credentials: "same-origin",
    headers: { Accept: "application/json" },
    signal: options?.signal,
    cache: "no-store",
  });

  if (!response.ok) {
    let body: Record<string, unknown> = {};
    try {
      body = (await response.json()) as Record<string, unknown>;
    } catch {
      /* ignore */
    }
    throw Object.assign(new Error(parseLaravelError(body, response.status).error.message), {
      envelope: parseLaravelError(body, response.status),
    });
  }

  const payload = (await response.json()) as Record<string, unknown>;
  return normalizeLaravelEnvelope<T>(payload);
}
