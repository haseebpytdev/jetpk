import type { DataSourceMetadata, DataSourceState } from "@/types/read-only-integration";
import { isStaleMetadata } from "@/lib/read-only/data-source";
import type { ReadOnlyErrorEnvelope } from "@/types/read-only-integration";
import { ReadOnlyServiceError } from "@/lib/read-only/read-only-service";

export type ModuleResult<TData> = {
  data: TData | null;
  state: DataSourceState;
  meta: DataSourceMetadata | null;
  error: ReadOnlyErrorEnvelope | null;
};

export function mapEnvelopeToModuleResult<TData>(
  data: TData,
  meta: DataSourceMetadata,
  emptyWhen?: (data: TData) => boolean,
): ModuleResult<TData> {
  const isEmpty = emptyWhen ? emptyWhen(data) : false;
  const state: DataSourceState = isEmpty ? "empty" : isStaleMetadata(meta) ? "stale" : "ready";

  return { data, state, meta, error: null };
}

export function mapServiceError(error: unknown): ModuleResult<never> {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    const state: DataSourceState =
      code === "unauthenticated"
        ? "unauthorized"
        : code === "forbidden"
          ? "forbidden"
          : code === "unavailable" || code === "timeout"
            ? "unavailable"
            : "error";

    return { data: null, state, meta: error.envelope.meta as DataSourceMetadata, error: error.envelope };
  }

  return {
    data: null,
    state: "error",
    meta: null,
    error: null,
  };
}
