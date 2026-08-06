import { laravelApiPath } from "@/services/flight-search";
import { pathAllowsCsrfAutoRetry, shouldRetryAfterCsrfExpired } from "./csrf-retry-policy.mjs";
import { normalizeNonJsonPayload } from "./response-payload-policy.mjs";
import type { ApiResult, LaravelRequestOptions } from "./types";
import { defaultErrorMessage, mapStatusToErrorCode } from "./errors";

const JSON_HEADERS = {
  Accept: "application/json",
  "X-Requested-With": "XMLHttpRequest",
} as const;

function readCookie(name: string): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

function clearXsrfCookie(): void {
  if (typeof document === "undefined") return;
  document.cookie = "XSRF-TOKEN=; Path=/; Max-Age=0; SameSite=Lax";
}

export async function ensureLaravelCsrfToken(forceRefresh = false): Promise<string | null> {
  if (!forceRefresh) {
    const existing = readCookie("XSRF-TOKEN");
    if (existing) return existing;
  } else {
    clearXsrfCookie();
  }

  try {
    const response = await fetch(laravelApiPath("/api/public/content/csrf-token"), {
      credentials: "include",
      headers: JSON_HEADERS,
      cache: "no-store",
    });
    if (!response.ok) return null;
    const body = (await response.json()) as { csrf_token?: string };
    return body.csrf_token ?? readCookie("XSRF-TOKEN");
  } catch {
    return null;
  }
}

function buildBody(options: LaravelRequestOptions): BodyInit | undefined {
  if (options.body !== undefined) return options.body;
  if (options.formData) return options.formData;
  if (options.formBody) {
    const formData = new FormData();
    Object.entries(options.formBody).forEach(([key, value]) => {
      if (value !== undefined) formData.append(key, value);
    });
    return formData;
  }
  if (options.json !== undefined) return JSON.stringify(options.json);
  return undefined;
}

function withTimeout(signal: AbortSignal | undefined, timeoutMs?: number): {
  signal: AbortSignal;
  cleanup: () => void;
} {
  if (!timeoutMs) {
    return { signal: signal ?? new AbortController().signal, cleanup: () => undefined };
  }

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);

  const onAbort = () => controller.abort();
  signal?.addEventListener("abort", onAbort);

  return {
    signal: controller.signal,
    cleanup: () => {
      clearTimeout(timer);
      signal?.removeEventListener("abort", onAbort);
    },
  };
}

async function parseResponsePayload(response: Response): Promise<unknown | null> {
  const contentType = response.headers.get("content-type") ?? "";
  const bodyText = await response.text();
  return normalizeNonJsonPayload(contentType, bodyText, defaultErrorMessage, response.status);
}

async function executeRequest<T>(
  path: string,
  options: LaravelRequestOptions,
  csrfForceRefresh = false,
): Promise<ApiResult<T>> {
  const method = options.method ?? "GET";
  const csrf = method !== "GET" ? await ensureLaravelCsrfToken(csrfForceRefresh) : null;
  const { signal, cleanup } = withTimeout(options.signal, options.timeoutMs);

  const headers: Record<string, string> = {
    ...JSON_HEADERS,
    ...(csrf ? { "X-XSRF-TOKEN": csrf } : {}),
    ...(options.json !== undefined ? { "Content-Type": "application/json" } : {}),
    ...options.headers,
  };

  try {
    const response = await fetch(laravelApiPath(path), {
      method,
      credentials: "include",
      headers,
      body: buildBody(options),
      signal,
      cache: method === "GET" ? "default" : "no-store",
    });

    const payload = await parseResponsePayload(response);

    if (!response.ok) {
      const errors = (payload as { errors?: Record<string, string[]> } | null)?.errors;
      const message =
        (payload as { message?: string } | null)?.message ?? defaultErrorMessage(response.status);

      return {
        ok: false,
        code: mapStatusToErrorCode(response.status),
        status: response.status,
        message,
        errors,
        data: payload,
      };
    }

    if (payload === null && response.status !== 204) {
      return {
        ok: false,
        code: "unknown",
        status: response.status,
        message: "Unexpected empty response from server.",
      };
    }

    return { ok: true, data: payload as T, status: response.status };
  } catch (error) {
    if (error instanceof DOMException && error.name === "AbortError") {
      return {
        ok: false,
        code: "aborted",
        status: 0,
        message: "Request cancelled.",
      };
    }
    return {
      ok: false,
      code: "network",
      status: 0,
      message: "Network error. Check your connection and try again.",
    };
  } finally {
    cleanup();
  }
}

/**
 * Typed Laravel AJAX boundary for browser requests through the /laravel proxy.
 */
export async function laravelRequest<T>(
  path: string,
  options: LaravelRequestOptions = {},
): Promise<ApiResult<T>> {
  const method = options.method ?? "GET";
  let result = await executeRequest<T>(path, options);

  if (
    !result.ok &&
    result.code === "network" &&
    method === "GET" &&
    options.retryOnNetworkError
  ) {
    result = await executeRequest<T>(path, options);
  }

  if (
    shouldRetryAfterCsrfExpired(result, method, options.retryCsrfOnce ?? false) &&
    pathAllowsCsrfAutoRetry(path)
  ) {
    result = await executeRequest<T>(path, options, true);
  }

  return result;
}

/** @deprecated Use laravelRequest — kept for auth migration compatibility. */
export async function laravelJsonFetch<T>(
  path: string,
  init?: RequestInit & { formBody?: Record<string, string | undefined>; retryCsrfOnce?: boolean },
): Promise<
  | { ok: true; data: T }
  | { ok: false; status: number; message: string; errors?: Record<string, string[]>; code?: string }
> {
  const result = await laravelRequest<T>(path, {
    method: (init?.method as LaravelRequestOptions["method"]) ?? "GET",
    body: init?.body ?? undefined,
    formBody: init?.formBody,
    headers: init?.headers as Record<string, string> | undefined,
    signal: init?.signal ?? undefined,
    retryCsrfOnce: init?.retryCsrfOnce,
  });

  if (result.ok) {
    return { ok: true, data: result.data };
  }

  return {
    ok: false,
    status: result.status,
    message: result.message,
    errors: result.errors,
    code: result.code,
  };
}

export function buildCookieHeader(cookiePairs: Array<{ name: string; value: string }>): string {
  return cookiePairs.map((cookie) => `${cookie.name}=${cookie.value}`).join("; ");
}
