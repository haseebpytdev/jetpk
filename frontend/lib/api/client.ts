import { appConfig } from "@/lib/config";

export type ApiErrorCode =
  | "network"
  | "unauthorized"
  | "forbidden"
  | "not_found"
  | "validation"
  | "server"
  | "unknown";

export type ApiResult<T> =
  | { ok: true; data: T }
  | { ok: false; error: { code: ApiErrorCode; message: string } };

export type ApiRequestOptions = {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  headers?: Record<string, string>;
  body?: unknown;
  signal?: AbortSignal;
};

/**
 * Typed Laravel API boundary for future integration.
 * Phase JP-FE-01 does not call live booking or supplier endpoints.
 */
export class LaravelApiClient {
  constructor(private readonly baseUrl: string = appConfig.laravelUrl) {}

  async request<T>(path: string, options: ApiRequestOptions = {}): Promise<ApiResult<T>> {
    const url = `${this.baseUrl.replace(/\/$/, "")}/${path.replace(/^\//, "")}`;

    try {
      const response = await fetch(url, {
        method: options.method ?? "GET",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          ...options.headers,
        },
        body: options.body ? JSON.stringify(options.body) : undefined,
        signal: options.signal,
        credentials: "include",
      });

      if (!response.ok) {
        return {
          ok: false,
          error: {
            code: mapStatusToErrorCode(response.status),
            message: `Request failed with status ${response.status}`,
          },
        };
      }

      const data = (await response.json()) as T;
      return { ok: true, data };
    } catch {
      return {
        ok: false,
        error: { code: "network", message: "Unable to reach the JetPakistan API." },
      };
    }
  }
}

function mapStatusToErrorCode(status: number): ApiErrorCode {
  if (status === 401) return "unauthorized";
  if (status === 403) return "forbidden";
  if (status === 404) return "not_found";
  if (status === 422) return "validation";
  if (status >= 500) return "server";
  return "unknown";
}

export const laravelApi = new LaravelApiClient();
