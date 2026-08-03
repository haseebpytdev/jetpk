export type ApiErrorCode =
  | "unauthorized"
  | "forbidden"
  | "not_found"
  | "conflict"
  | "csrf_expired"
  | "validation"
  | "rate_limit"
  | "server"
  | "network"
  | "aborted"
  | "unknown";

export type LaravelValidationErrors = Record<string, string[]>;

export type LaravelRequestOptions = {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  json?: Record<string, unknown>;
  formBody?: Record<string, string | undefined>;
  formData?: FormData;
  body?: BodyInit;
  headers?: Record<string, string>;
  signal?: AbortSignal;
  timeoutMs?: number;
  retryCsrfOnce?: boolean;
  retryOnNetworkError?: boolean;
};

export type ApiResult<T> =
  | { ok: true; data: T; status: number }
  | {
      ok: false;
      code: ApiErrorCode;
      status: number;
      message: string;
      errors?: LaravelValidationErrors;
      data?: unknown;
    };
