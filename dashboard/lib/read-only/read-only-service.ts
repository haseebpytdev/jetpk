import { createReadOnlyErrorEnvelope, mapHttpStatusToErrorCode, sanitizeErrorMessage } from "@/lib/read-only/error-envelope";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { resolveDataSourceMode } from "@/lib/read-only/data-source";
import type {
  DataSourceMetadata,
  ReadOnlyErrorEnvelope,
  ReadOnlyResponseEnvelope,
} from "@/types/read-only-integration";

export type ReadOnlyFetchOptions = {
  signal?: AbortSignal;
  metadata?: Partial<DataSourceMetadata>;
};

export type ReadOnlyAdapter<TQuery, TResult> = {
  readonly mode: "fixture" | "laravelReadOnly";
  fetch(query: TQuery, options?: ReadOnlyFetchOptions): Promise<ReadOnlyResponseEnvelope<TResult>>;
};

export class ReadOnlyServiceError extends Error {
  readonly envelope: ReadOnlyErrorEnvelope;

  constructor(envelope: ReadOnlyErrorEnvelope) {
    super(envelope.error.message);
    this.name = "ReadOnlyServiceError";
    this.envelope = envelope;
  }
}

export function createReadOnlyService<TQuery, TResult>(params: {
  module: string;
  fixtureAdapter: ReadOnlyAdapter<TQuery, TResult>;
  laravelAdapter?: ReadOnlyAdapter<TQuery, TResult>;
}) {
  const { fixtureAdapter, laravelAdapter } = params;

  async function fetchReadOnly(
    query: TQuery,
    options?: ReadOnlyFetchOptions,
  ): Promise<ReadOnlyResponseEnvelope<TResult>> {
    const mode = resolveDataSourceMode();

    if (mode === "unavailable") {
      throw new ReadOnlyServiceError(
        createReadOnlyErrorEnvelope({
          code: "unavailable",
          referenceIdSafe: `${params.module.toUpperCase()}-UNAVAILABLE`,
        }),
      );
    }

    if (mode === "laravelReadOnly") {
      if (!laravelAdapter) {
        throw new ReadOnlyServiceError(
          createReadOnlyErrorEnvelope({
            code: "unavailable",
            referenceIdSafe: `${params.module.toUpperCase()}-NO-LIVE-ADAPTER`,
            message: "Laravel read-only adapter is not configured for this module yet.",
          }),
        );
      }
      return laravelAdapter.fetch(query, options);
    }

    return fixtureAdapter.fetch(query, options);
  }

  return {
    fetchReadOnly,
    wrapFixtureResult(data: TResult, options?: ReadOnlyFetchOptions): ReadOnlyResponseEnvelope<TResult> {
      return createReadOnlyEnvelope({ data, metadata: options?.metadata });
    },
  };
}

export async function fetchLaravelReadOnly<T>(
  url: string,
  options?: RequestInit & ReadOnlyFetchOptions,
): Promise<ReadOnlyResponseEnvelope<T>> {
  const response = await fetch(url, {
    ...options,
    method: "GET",
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      ...(options?.headers ?? {}),
    },
    signal: options?.signal,
  });

  if (!response.ok) {
    const code = mapHttpStatusToErrorCode(response.status);
    let message: string | undefined;
    try {
      const body = (await response.json()) as { message?: string };
      if (body.message) {
        message = sanitizeErrorMessage(body.message);
      }
    } catch {
      /* ignore parse errors */
    }
    throw new ReadOnlyServiceError(
      createReadOnlyErrorEnvelope({
        code,
        referenceIdSafe: `HTTP-${response.status}`,
        message,
      }),
    );
  }

  const payload = (await response.json()) as ReadOnlyResponseEnvelope<T>;
  return payload;
}

/** Explicit guard — read-only services must never expose mutation verbs. */
export const READ_ONLY_HTTP_METHODS = ["GET"] as const;

export function assertReadOnlyHttpMethod(method: string): void {
  if (!READ_ONLY_HTTP_METHODS.includes(method.toUpperCase() as "GET")) {
    throw new Error(`Read-only integration prohibits HTTP method: ${method}`);
  }
}
