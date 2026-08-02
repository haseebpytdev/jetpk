export type LaravelValidationErrors = Record<string, string[]>;

export type ApiErrorCode =
  | "network"
  | "timeout"
  | "aborted"
  | "unauthorized"
  | "forbidden"
  | "not_found"
  | "conflict"
  | "csrf_expired"
  | "validation"
  | "rate_limit"
  | "server"
  | "unknown";

export type ApiError = {
  code: ApiErrorCode;
  status: number;
  message: string;
  errors?: LaravelValidationErrors;
  data?: unknown;
};

export type ApiSuccess<T> = {
  ok: true;
  data: T;
  status: number;
};

export type ApiFailure = {
  ok: false;
} & ApiError;

export type ApiResult<T> = ApiSuccess<T> | ApiFailure;

export type LaravelRequestOptions = {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  headers?: Record<string, string>;
  body?: BodyInit;
  json?: unknown;
  formBody?: Record<string, string | undefined>;
  formData?: FormData;
  signal?: AbortSignal;
  timeoutMs?: number;
  /** When true, safe GET retries once on network failure. */
  retryOnNetworkError?: boolean;
  /**
   * When true, a single CSRF refresh may occur before retrying a failed mutation.
   * Never use for payment or booking mutations.
   */
  retryCsrfOnce?: boolean;
};
